<?php
/**
 * forgot_password.php
 * Klientas įveda el. paštą, jam siunčiama nuoroda su laikinu tokenu
 * slaptažodžio atstatymui. Tokenas galioja 1 valandą.
 */

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($data['email'] ?? ''));

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false, 'msg'=>'Įveskite teisingą el. paštą']); exit;
}

$file = __DIR__ . '/users.json';
$users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$userFound = null;
foreach ($users as &$u) {
    if ($u['email'] === $email) {
        $userFound = &$u;
        break;
    }
}

// Saugumo sumetimais grąžiname tą pačią žinutę nepriklausomai nuo to,
// ar paskyra egzistuoja — neturime atskleisti, kokie email registruoti.
$genericMsg = 'Jei toks el. paštas registruotas, atsiųsime nuorodą slaptažodžio atstatymui.';

if (!$userFound) {
    echo json_encode(['ok'=>true, 'msg'=>$genericMsg]); exit;
}

$resetToken = bin2hex(random_bytes(32));
$userFound['reset_token'] = $resetToken;
$userFound['reset_expires'] = time() + 3600; // 1 valanda

file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

function logMailErrorFP($msg) {
    @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] '.$msg."\n", FILE_APPEND);
}

$cfg = file_exists(__DIR__.'/settings.json') ? json_decode(file_get_contents(__DIR__.'/settings.json'), true) : [];
$GMAIL_USER = $cfg['gmail_user'] ?? '';
$GMAIL_PASS = $cfg['gmail_pass'] ?? '';
$SITE_URL   = $cfg['site_url']   ?? '';
$SITE_NAME  = $cfg['site_name']  ?? 'market';

try {
    if (!empty($GMAIL_USER) && !empty($GMAIL_PASS)) {
        $phpmailerPath = __DIR__.'/PHPMailer/src/';
        if (file_exists($phpmailerPath.'Exception.php') && file_exists($phpmailerPath.'PHPMailer.php') && file_exists($phpmailerPath.'SMTP.php')) {
            require_once $phpmailerPath.'Exception.php';
            require_once $phpmailerPath.'PHPMailer.php';
            require_once $phpmailerPath.'SMTP.php';

            $resetLink = rtrim($SITE_URL, '/') . '/reset_password.html?token=' . $resetToken;

            $html = "<div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto'>
<div style='background:#0B1929;color:white;padding:24px 30px;border-radius:10px 10px 0 0'><h1 style='margin:0;font-size:20px'>Slaptažodžio atstatymas</h1></div>
<div style='background:white;padding:24px 30px;border:1px solid #e2e8f0'>
<p>Sveiki, {$userFound['name']}!</p>
<p>Gavome prašymą atstatyti jūsų paskyros slaptažodį. Paspauskite mygtuką žemiau, kad nustatytumėte naują slaptažodį:</p>
<div style='text-align:center;margin:24px 0'><a href='{$resetLink}' style='background:#FF5C35;color:white;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold'>Atstatyti slaptažodį</a></div>
<p style='font-size:12px;color:#94a3b8'>Nuoroda galioja 1 valandą. Jei jūs neprašėte slaptažodžio atstatymo, ignoruokite šį laišką.</p>
<p style='font-size:12px;color:#94a3b8'>Jei mygtukas neveikia, nukopijuokite šią nuorodą į naršyklę:<br>{$resetLink}</p>
</div></div>";

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $GMAIL_USER;
            $mail->Password = $GMAIL_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($GMAIL_USER, $SITE_NAME);
            $mail->addReplyTo($GMAIL_USER, $SITE_NAME);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Slaptažodžio atstatymas';
            $mail->Body = $html;
            $mail->AltBody = trim(strip_tags($html));
            $mail->send();
        } else {
            logMailErrorFP('PHPMailer files not found at '.$phpmailerPath);
        }
    }
} catch (\Throwable $e) {
    logMailErrorFP('Password reset email failed: '.$e->getMessage());
}

echo json_encode(['ok'=>true, 'msg'=>$genericMsg]);
