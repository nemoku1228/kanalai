<?php
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['admin'])) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }

$cfg = file_exists(__DIR__.'/settings.json') ? json_decode(file_get_contents(__DIR__.'/settings.json'), true) : [];
$GMAIL_USER  = $cfg['gmail_user']  ?? '';
$GMAIL_PASS  = $cfg['gmail_pass']  ?? '';
$SITE_URL    = $cfg['site_url']    ?? '';
$SITE_NAME   = $cfg['site_name']   ?? 'Pneumatinės Pagalvės';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) { http_response_code(400); echo json_encode(['ok'=>false]); exit; }

$file   = __DIR__.'/orders.json';
$orders = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$updatedOrder=null; $trackingAdded=false; $statusChanged=false; $oldStatus='';
foreach ($orders as &$order) {
    if ($order['id']===$data['id']) {
        $oldStatus=$order['status'];
        if(isset($data['status'])&&$data['status']!==$order['status']){$order['status']=$data['status'];$statusChanged=true;}
        if(isset($data['tracking'])&&$data['tracking']!==$order['tracking']){$order['tracking']=$data['tracking'];$trackingAdded=!empty($data['tracking']);}
        if(isset($data['notes'])) $order['notes']=$data['notes'];
        $updatedOrder=$order; break;
    }
}
unset($order);
$saveOk = file_put_contents($file, json_encode($orders, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);

if ($saveOk === false) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'msg'=>'Nepavyko įrašyti pakeitimų']);
    exit;
}

function logMailErrorUO($msg) {
    @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] '.$msg."\n", FILE_APPEND);
}

/**
 * Saugus el. laiško siuntimas su pasirenkamu PDF priedu.
 * Klaidos NIEKADA neturi sustabdyti viso skripto — visada loguojamos.
 */
function sendMailSafe($from, $pass, $to, $subject, $html, $siteName, $attachmentPath = null, $attachmentName = null) {
    $phpmailerPath = __DIR__.'/PHPMailer/src/';
    if (!file_exists($phpmailerPath.'Exception.php') || !file_exists($phpmailerPath.'PHPMailer.php') || !file_exists($phpmailerPath.'SMTP.php')) {
        logMailErrorUO('PHPMailer failai nerasti: '.$phpmailerPath);
        return false;
    }
    try {
        require_once $phpmailerPath.'Exception.php';
        require_once $phpmailerPath.'PHPMailer.php';
        require_once $phpmailerPath.'SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $from;
        $mail->Password = $pass;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, $siteName);
        $mail->addReplyTo($from, $siteName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = trim(strip_tags($html));
        if ($attachmentPath && file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath, $attachmentName ?? basename($attachmentPath));
        }
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        logMailErrorUO('sendMailSafe failed: '.$e->getMessage());
        return false;
    }
}

if ($updatedOrder && !empty($updatedOrder['customer']['email']) && !empty($GMAIL_USER)) {
    $email = $updatedOrder['customer']['email'];
    $name  = $updatedOrder['customer']['name'];
    $id    = $updatedOrder['id'];

    if ($trackingAdded) {
        $t = $updatedOrder['tracking'];
        $html = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><div style='background:#1e293b;color:white;padding:20px 30px;border-radius:10px'><h1 style='margin:0'>Jusu siunta issiusta!</h1><p style='color:#93c5fd'>{$id}</p></div><div style='background:white;padding:20px 30px;border:1px solid #e2e8f0'><p>Sveiki, <strong>{$name}</strong>!</p><div style='background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px;margin:15px 0;text-align:center'><p style='margin:0 0 5px;font-size:13px;color:#166534;font-weight:bold'>SEKIMO NUMERIS</p><p style='margin:0;font-size:24px;font-weight:bold;color:#15803d;font-family:monospace'>{$t}</p></div></div><div style='text-align:center;padding:15px 30px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:0 0 10px 10px'><a href='{$SITE_URL}/track.html' style='background:#1e293b;color:white;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:bold'>Sekti siunta</a></div></div>";
        sendMailSafe($GMAIL_USER, $GMAIL_PASS, $email, "Jusu siunta issiusta - {$id}", $html, $SITE_NAME);
    }

    if ($statusChanged && $updatedOrder['status'] === 'Completed') {
        $html = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#15803d;color:white;padding:24px;border-radius:10px'><h1>Siunta pristatyta!</h1><p>Sveiki, <strong>{$name}</strong>! Jusu uzsakymas <strong>{$id}</strong> sekmingai pristatytas. Klausimai? +370 690 90403</p></div>";
        sendMailSafe($GMAIL_USER, $GMAIL_PASS, $email, "Siunta pristatyta - {$id}", $html, $SITE_NAME);
    }

    if ($statusChanged && $updatedOrder['status'] === 'Cancelled') {
        // ── KREDITINĖ SĄSKAITA — generuojama tik jei buvo originali PVM sąskaita ──
        $creditPdfPath = null;
        if (!empty($updatedOrder['invoice'])) {
            try {
                require_once __DIR__.'/generate_invoice.php';
                $creditResult = generateCreditInvoicePdf($updatedOrder);
                if ($creditResult) $creditPdfPath = $creditResult['path'];
            } catch (\Throwable $e) {
                logMailErrorUO('Credit invoice generation failed: '.$e->getMessage());
            }
        }

        $creditNote = $creditPdfPath
            ? "<p style='font-size:13px;color:#fecaca'>Kreditinė sąskaita-faktūra pridėta prie šio laiško.</p>"
            : '';
        $html = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#dc2626;color:white;padding:24px;border-radius:10px'><h1>Uzsakymas atsauktas</h1><p>Sveiki, <strong>{$name}</strong>. Jusu uzsakymas <strong>{$id}</strong> buvo atsauktas. Del informacijos skambinkite +370 690 90403.</p>{$creditNote}</div>";
        sendMailSafe(
            $GMAIL_USER, $GMAIL_PASS, $email, "Uzsakymas atsauktas - {$id}", $html, $SITE_NAME,
            $creditPdfPath, $creditPdfPath ? 'Kreditine_saskaita_'.$id.'.pdf' : null
        );
    }
}

echo json_encode(['ok'=>true]);
