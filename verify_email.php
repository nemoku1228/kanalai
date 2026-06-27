<?php
/**
 * verify_email.php
 * Patvirtina vartotojo el. paštą, kai jis paspaudžia nuorodą iš registracijos
 * laiško. Po sėkmingo patvirtinimo automatiškai prijungia vartotoją.
 */

$token = trim($_GET['token'] ?? '');

$file = __DIR__ . '/users.json';
$users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$verifiedUser = null;
foreach ($users as &$u) {
    if (($u['verify_token'] ?? '') === $token && $token !== '') {
        $u['verified'] = true;
        $u['verify_token'] = null; // tokenas naudojamas tik vieną kartą
        $verifiedUser = $u;
        break;
    }
}
unset($u);

if ($verifiedUser) {
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    session_start();
    $_SESSION['user_id']    = $verifiedUser['id'];
    $_SESSION['user_email'] = $verifiedUser['email'];
    $_SESSION['user_name']  = $verifiedUser['name'];
}

$success = $verifiedUser !== null;
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>El. pašto patvirtinimas</title>
<style>
body{font-family:Arial,sans-serif;background:#F7F8FA;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:white;border-radius:14px;padding:40px;max-width:420px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.icon{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:28px}
.icon.success{background:#dcfce7;color:#16a34a}
.icon.error{background:#fee2e2;color:#dc2626}
h1{font-size:20px;margin:0 0 10px}
p{color:#64748b;font-size:14px;line-height:1.6}
a.btn{display:inline-block;margin-top:20px;background:#FF5C35;color:white;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold}
</style>
</head>
<body>
<div class="card">
<?php if ($success): ?>
<div class="icon success">&#10003;</div>
<h1>El. paštas patvirtintas!</h1>
<p>Jūsų paskyra aktyvuota. Galite naudotis visomis svetainės funkcijomis.</p>
<a href="index.html" class="btn">Eiti į parduotuvę</a>
<?php else: ?>
<div class="icon error">&#10007;</div>
<h1>Nuoroda negaliojanti</h1>
<p>Šis patvirtinimo nuoroda nebegalioja arba jau buvo panaudota. Jei jau bandėte patvirtinti anksčiau, tiesiog prisijunkite.</p>
<a href="index.html" class="btn">Grįžti į parduotuvę</a>
<?php endif; ?>
</div>
</body>
</html>
