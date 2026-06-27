<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['email']) || empty($data['password']) || empty($data['name'])) {
    echo json_encode(['ok'=>false,'msg'=>'Trūksta duomenų']); exit;
}

if (!filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false,'msg'=>'Įveskite teisingą el. pašto adresą']); exit;
}

$email = strtolower(trim($data['email']));
$name  = trim($data['name']);
$pass  = password_hash($data['password'], PASSWORD_DEFAULT);

$file  = __DIR__.'/users.json';
$users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

foreach ($users as $u) {
    if ($u['email'] === $email) {
        echo json_encode(['ok'=>false,'msg'=>'Toks el. paštas jau užregistruotas']); exit;
    }
}

$verifyToken = bin2hex(random_bytes(32));

$user = [
    'id'            => 'USR-' . substr(md5($email . time()), 0, 8),
    'email'         => $email,
    'name'          => $name,
    'password'      => $pass,
    'address'       => ['city'=>'','street'=>'','zipcode'=>''],
    'created'       => date('Y-m-d H:i:s'),
    'verified'      => false,
    'verify_token'  => $verifyToken,
];
$users[] = $user;
$saveOk = file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

if ($saveOk === false) {
    echo json_encode(['ok'=>false, 'msg'=>'Nepavyko išsaugoti paskyros']); exit;
}

// ── PATVIRTINIMO LAIŠKO SIUNTIMAS — klaidos nestabdo registracijos atsako ──
function logMailErrorReg($msg) {
    @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] '.$msg."\n", FILE_APPEND);
}

$cfg = file_exists(__DIR__.'/settings.json') ? json_decode(file_get_contents(__DIR__.'/settings.json'), true) : [];
$GMAIL_USER = $cfg['gmail_user'] ?? '';
$GMAIL_PASS = $cfg['gmail_pass'] ?? '';
$SITE_URL   = $cfg['site_url']   ?? '';
$SITE_NAME  = $cfg['site_name']  ?? 'market';

$emailSent = false;
try {
    if (!empty($GMAIL_USER) && !empty($GMAIL_PASS)) {
        $phpmailerPath = __DIR__.'/PHPMailer/src/';
        if (file_exists($phpmailerPath.'Exception.php') && file_exists($phpmailerPath.'PHPMailer.php') && file_exists($phpmailerPath.'SMTP.php')) {
            require_once $phpmailerPath.'Exception.php';
            require_once $phpmailerPath.'PHPMailer.php';
            require_once $phpmailerPath.'SMTP.php';

            $verifyLink = rtrim($SITE_URL, '/') . '/verify_email.php?token=' . $verifyToken;

            $html = "<div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto'>
<div style='background:#0B1929;color:white;padding:24px 30px;border-radius:10px 10px 0 0'><h1 style='margin:0;font-size:20px'>Sveiki, {$name}!</h1></div>
<div style='background:white;padding:24px 30px;border:1px solid #e2e8f0'>
<p>Dėkojame, kad registravotės {$SITE_NAME}. Norėdami patvirtinti savo el. paštą ir aktyvuoti paskyrą, paspauskite mygtuką žemiau:</p>
<div style='text-align:center;margin:24px 0'><a href='{$verifyLink}' style='background:#FF5C35;color:white;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold'>Patvirtinti el. paštą</a></div>
<p style='font-size:12px;color:#94a3b8'>Jei mygtukas neveikia, nukopijuokite šią nuorodą į naršyklę:<br>{$verifyLink}</p>
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
            $mail->Subject = 'Patvirtinkite savo el. paštą';
            $mail->Body = $html;
            $mail->AltBody = trim(strip_tags($html));
            $mail->send();
            $emailSent = true;
        } else {
            logMailErrorReg('PHPMailer files not found at '.$phpmailerPath);
        }
    }
} catch (\Throwable $e) {
    logMailErrorReg('Registration verification email failed: '.$e->getMessage());
}

// SVARBU: sesija NEPRADEDAMA čia — vartotojas turi patvirtinti el. paštą
// prieš galėdamas prisijungti (žr. login.php patikrą).
echo json_encode([
    'ok' => true,
    'name' => $user['name'],
    'needs_verification' => true,
    'email_sent' => $emailSent,
]);
