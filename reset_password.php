<?php
/**
 * reset_password.php
 * Priima tokeną (iš el. laiško nuorodos) ir naują slaptažodį,
 * patikrina tokeno galiojimą (1 valanda) ir atnaujina slaptažodį.
 */

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$token = trim($data['token'] ?? '');
$newPassword = $data['password'] ?? '';

if (!$token || !$newPassword) {
    echo json_encode(['ok'=>false, 'msg'=>'Trūksta duomenų']); exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['ok'=>false, 'msg'=>'Slaptažodis turi būti bent 6 simbolių']); exit;
}

$file = __DIR__ . '/users.json';
$users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$userFound = null;
foreach ($users as &$u) {
    if (($u['reset_token'] ?? '') === $token && $token !== '') {
        $userFound = &$u;
        break;
    }
}

if (!$userFound) {
    echo json_encode(['ok'=>false, 'msg'=>'Nuoroda negaliojanti arba pasenusi']); exit;
}

if (($userFound['reset_expires'] ?? 0) < time()) {
    echo json_encode(['ok'=>false, 'msg'=>'Nuoroda nebegalioja. Pakartokite slaptažodžio atstatymo užklausą.']); exit;
}

$userFound['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
$userFound['reset_token'] = null;
$userFound['reset_expires'] = null;

file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

echo json_encode(['ok'=>true, 'msg'=>'Slaptažodis sėkmingai pakeistas! Galite prisijungti.']);
