<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

// ── Nustatymai iš settings.json (keičiami per admin panelę) ──────
$cfg = file_exists(__DIR__.'/settings.json') ? json_decode(file_get_contents(__DIR__.'/settings.json'), true) : [];
$GMAIL_USER  = $cfg['gmail_user']  ?? '';
$GMAIL_PASS  = $cfg['gmail_pass']  ?? '';
$ADMIN_EMAIL = $cfg['admin_email'] ?? '';
$SITE_URL    = $cfg['site_url']    ?? '';
$SITE_NAME   = $cfg['site_name']   ?? 'Pneumatinės Pagalvės';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['id']) || empty($data['cart'])) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'msg'=>'Trūksta užsakymo duomenų']);
    exit;
}

// ── El. pašto formato patikra (galioja ir perkant be registracijos) ──
$custEmail = trim($data['customer']['email'] ?? '');
if ($custEmail === '' || !filter_var($custEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'msg'=>'Neteisingas el. pašto formatas', 'email_error'=>true]);
    exit;
}

// ── Telefono numerio patikra (8–15 skaitmenų, leidžiamas + priekyje) ──
$custPhoneDigits = preg_replace('/[\s\-()]/', '', trim($data['customer']['phone'] ?? ''));
if (!preg_match('/^\+?\d{8,15}$/', $custPhoneDigits)) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'msg'=>'Neteisingas telefono numeris', 'phone_error'=>true]);
    exit;
}

// ── KIEKIO/LIKUČIO PATIKRA prieš išsaugant užsakymą ───────────────
$productsFile = __DIR__.'/products.json';
$products = file_exists($productsFile) ? json_decode(file_get_contents($productsFile), true) : [];
$stockErrors = [];

foreach ($data['cart'] as $item) {
    $prod = null;
    foreach ($products as $p) {
        if (($p['id'] ?? null) === ($item['id'] ?? null)) { $prod = $p; break; }
    }
    if ($prod !== null) {
        $stock = (int)($prod['stock'] ?? 0);
        if ($stock <= 0) {
            $stockErrors[] = $item['name'] ?? $item['id'];
        } elseif ($stock < (int)($item['quantity'] ?? 1)) {
            $stockErrors[] = ($item['name'] ?? $item['id']) . " (likę tik {$stock} vnt.)";
        }
    }
}

if (!empty($stockErrors)) {
    http_response_code(409);
    echo json_encode(['ok'=>false, 'msg'=>'Prekių trūksta sandėlyje: '.implode(', ', $stockErrors), 'stock_error'=>true]);
    exit;
}

// ── UŽSAKYMO IŠSAUGOJIMAS (svarbiausia dalis — vykdoma PIRMA, prieš email) ──
$file   = __DIR__.'/orders.json';
$orders = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
if (!is_array($orders)) $orders = [];

$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($clientIp, ',') !== false) $clientIp = trim(explode(',', $clientIp)[0]);

$order = [
    'id'       => $data['id'],
    'date'     => date('Y-m-d H:i:s'),
    'customer' => $data['customer'],
    'cart'     => $data['cart'],
    'total'    => $data['total'],
    'delivery' => $data['delivery'],
    'terminal' => $data['terminal'] ?? null,
    'invoice'  => $data['invoice'] ?? null,
    'status'   => 'Submitted',
    'tracking' => '',
    'notes'    => '',
    'ip'       => $clientIp,
];

$orders[] = $order;
$saveOk = file_put_contents($file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

if ($saveOk === false) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'msg'=>'Nepavyko įrašyti užsakymo į serverį']);
    exit;
}

// ── PREKIŲ LIKUČIO SUMAŽINIMAS ────────────────────────────────────
$stockChanged = false;
foreach ($products as &$p) {
    foreach ($order['cart'] as $item) {
        if (($p['id'] ?? null) === ($item['id'] ?? null)) {
            $p['stock'] = max(0, (int)($p['stock'] ?? 0) - (int)($item['quantity'] ?? 1));
            $stockChanged = true;
        }
    }
}
unset($p);
if ($stockChanged) {
    file_put_contents($productsFile, json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ── SĄSKAITOS FAKTŪROS GENERAVIMAS (jei klientas pažymėjo norą) ────
$invoicePdfPath = null;
if (!empty($order['invoice']) && !empty($order['invoice']['company_name'])) {
    try {
        require_once __DIR__.'/generate_invoice.php';
        $invoiceResult = generateInvoicePdf($order, $order['invoice'], $cfg);
        if ($invoiceResult) {
            $invoicePdfPath = $invoiceResult['path'];
            $order['invoice_number'] = $invoiceResult['invoice_number'];

            // Atnaujinam orders.json su sąskaitos numeriu (kad eksportas/admin matytų)
            $ordersForUpdate = json_decode(file_get_contents($file), true) ?: [];
            foreach ($ordersForUpdate as &$ou) {
                if ($ou['id'] === $order['id']) { $ou['invoice_number'] = $invoiceResult['invoice_number']; break; }
            }
            unset($ou);
            file_put_contents($file, json_encode($ordersForUpdate, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
    } catch (\Throwable $e) {
        @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] Invoice generation failed: '.$e->getMessage()."\n", FILE_APPEND);
    }
}

// ── EL. LAIŠKŲ SIUNTIMAS — klaidos NIEKADA nestabdo užsakymo atsako ──
function logMailError($msg) {
    @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] '.$msg."\n", FILE_APPEND);
}

try {
    if (!empty($GMAIL_USER) && !empty($GMAIL_PASS)) {
        $phpmailerPath = __DIR__.'/PHPMailer/src/';
        if (file_exists($phpmailerPath.'Exception.php') && file_exists($phpmailerPath.'PHPMailer.php') && file_exists($phpmailerPath.'SMTP.php')) {
            require_once $phpmailerPath.'Exception.php';
            require_once $phpmailerPath.'PHPMailer.php';
            require_once $phpmailerPath.'SMTP.php';

            $itemsHtml = '';
            foreach ($order['cart'] as $item) {
                $sum = number_format($item['price'] * $item['quantity'], 2);
                $itemsHtml .= "<tr><td style='padding:6px 10px;border-bottom:1px solid #eee'>{$item['name']}</td><td style='padding:6px 10px;border-bottom:1px solid #eee;text-align:center'>{$item['quantity']}</td><td style='padding:6px 10px;border-bottom:1px solid #eee;text-align:right'>{$sum} €</td></tr>";
            }
            $delLabels = ['courier'=>'Kurjeriu (+5.90 €)','post'=>'Paštomatas (+4.90 €)','bus'=>'Autobusų siuntos (+7.00 €)'];
            $delLabel = $delLabels[$order['delivery']] ?? $order['delivery'];

            $adminHtml = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
<div style='background:#1e293b;color:white;padding:20px 30px;border-radius:10px 10px 0 0'><h1 style='margin:0;font-size:20px'>Naujas uzsakymas!</h1><p style='margin:5px 0 0;color:#93c5fd'>{$order['id']} - {$order['date']}</p></div>
<div style='background:#f8fafc;padding:20px 30px;border:1px solid #e2e8f0'><p><strong>{$order['customer']['name']} {$order['customer']['surname']}</strong></p><p style='color:#64748b'>{$order['customer']['phone']}</p><p style='color:#64748b'>{$order['customer']['email']}</p><p style='color:#64748b'>{$order['customer']['city']}, {$order['customer']['street']}, {$order['customer']['zipcode']}</p><p style='color:#64748b'>{$delLabel}</p>" . (!empty($order['invoice']) ? "<p style='color:#16a34a;font-weight:bold'>📄 Pageidauja PVM sąskaitos: {$order['invoice']['company_name']} (kodas {$order['invoice']['company_code']})</p>" : "") . "<p style='color:#94a3b8;font-size:12px'>IP: {$clientIp}</p></div>
<div style='background:white;padding:20px 30px;border:1px solid #e2e8f0;border-top:none'><table style='width:100%;border-collapse:collapse;font-size:14px'><thead><tr style='background:#f1f5f9'><th style='padding:8px 10px;text-align:left'>Preke</th><th style='padding:8px 10px;text-align:center'>Kiekis</th><th style='padding:8px 10px;text-align:right'>Suma</th></tr></thead><tbody>{$itemsHtml}</tbody></table><div style='text-align:right;font-size:16px;font-weight:bold;margin-top:10px;padding-top:10px;border-top:2px solid #e2e8f0'>Viso: {$order['total']}</div></div>
<div style='background:#f8fafc;padding:15px 30px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px;text-align:center'><a href='{$SITE_URL}/admin.php' style='background:#2563eb;color:white;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:bold'>Atidaryti admin panele</a></div></div>";

            $clientHtml = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
<div style='background:#1e293b;color:white;padding:20px 30px;border-radius:10px 10px 0 0'><h1 style='margin:0;font-size:20px'>Uzsakymas gautas!</h1><p style='margin:5px 0 0;color:#93c5fd'>Aciu, {$order['customer']['name']}!</p></div>
<div style='background:white;padding:20px 30px;border:1px solid #e2e8f0'><p>Jusu uzsakymas <strong>{$order['id']}</strong> sekmingai gautas ir bus apdorotas artimiausia metu.</p><table style='width:100%;border-collapse:collapse;font-size:14px;margin-top:15px'><thead><tr style='background:#f1f5f9'><th style='padding:8px 10px;text-align:left'>Preke</th><th style='padding:8px 10px;text-align:center'>Kiekis</th><th style='padding:8px 10px;text-align:right'>Suma</th></tr></thead><tbody>{$itemsHtml}</tbody></table><div style='text-align:right;font-size:16px;font-weight:bold;margin-top:10px;padding-top:10px;border-top:2px solid #e2e8f0'>Viso: {$order['total']}</div></div>
<div style='background:#f8fafc;padding:15px 30px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px;text-align:center'><a href='{$SITE_URL}/track.html' style='background:#1e293b;color:white;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:bold'>Sekti uzsakyma</a><p style='margin:10px 0 0;font-size:12px;color:#94a3b8'>Klausimai? +370 690 90403</p></div></div>";

            $mail1 = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail1->isSMTP();
                $mail1->Host = 'smtp.gmail.com';
                $mail1->SMTPAuth = true;
                $mail1->Username = $GMAIL_USER;
                $mail1->Password = $GMAIL_PASS;
                $mail1->SMTPSecure = 'tls';
                $mail1->Port = 587;
                $mail1->CharSet = 'UTF-8';
                $mail1->setFrom($GMAIL_USER, $SITE_NAME);
                $mail1->addReplyTo($GMAIL_USER, $SITE_NAME);
                $mail1->addAddress($ADMIN_EMAIL);
                $mail1->isHTML(true);
                $mail1->Subject = "Naujas uzsakymas {$order['id']}";
                $mail1->Body = $adminHtml;
                $mail1->AltBody = trim(strip_tags($adminHtml));
                $mail1->send();
            } catch (\Throwable $e) {
                logMailError('Admin email failed: '.$e->getMessage());
            }

            if (!empty($order['customer']['email'])) {
                $mail2 = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail2->isSMTP();
                    $mail2->Host = 'smtp.gmail.com';
                    $mail2->SMTPAuth = true;
                    $mail2->Username = $GMAIL_USER;
                    $mail2->Password = $GMAIL_PASS;
                    $mail2->SMTPSecure = 'tls';
                    $mail2->Port = 587;
                    $mail2->CharSet = 'UTF-8';
                    $mail2->setFrom($GMAIL_USER, $SITE_NAME);
                    $mail2->addReplyTo($GMAIL_USER, $SITE_NAME);
                    $mail2->addAddress($order['customer']['email']);
                    $mail2->isHTML(true);
                    $mail2->Subject = "Uzsakymas gautas - {$order['id']}";
                    $mail2->Body = $clientHtml;
                    $mail2->AltBody = trim(strip_tags($clientHtml));
                    if ($invoicePdfPath && file_exists($invoicePdfPath)) {
                        $mail2->addAttachment($invoicePdfPath, 'PVM_saskaita_'.$order['id'].'.pdf');
                    }
                    $mail2->send();
                } catch (\Throwable $e) {
                    logMailError('Client email failed: '.$e->getMessage());
                }
            }
        } else {
            logMailError('PHPMailer files not found at '.$phpmailerPath);
        }
    }
} catch (\Throwable $e) {
    logMailError('Unexpected mail block error: '.$e->getMessage());
}

// ── ATSAKYMAS — visada grąžinamas, nepriklausomai nuo email rezultato ──
echo json_encode(['ok'=>true, 'id'=>$order['id']]);
