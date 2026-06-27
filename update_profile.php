<?php
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'msg'=>'Neprisijungęs']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$file  = 'users.json';
$users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

foreach ($users as &$u) {
    if ($u['id'] === $_SESSION['user_id']) {
        if (!empty($data['city']))    $u['address']['city']    = trim($data['city']);
        if (!empty($data['street']))  $u['address']['street']  = trim($data['street']);
        if (!empty($data['zipcode'])) $u['address']['zipcode'] = trim($data['zipcode']);
        if (!empty($data['name']))    { $u['name'] = trim($data['name']); $_SESSION['user_name'] = $u['name']; }
        if (!empty($data['new_password'])) {
            if (empty($data['old_password']) || !password_verify($data['old_password'], $u['password'])) {
                echo json_encode(['ok'=>false,'msg'=>'Neteisingas dabartinis slaptažodis']); exit;
            }
            $u['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }
        file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        echo json_encode(['ok'=>true]);
        exit;
    }
}
echo json_encode(['ok'=>false,'msg'=>'Vartotojas nerastas']);
