<?php
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['ok'=>false]); exit; }
$users = json_decode(file_get_contents('users.json'), true) ?? [];
foreach ($users as $u) {
    if ($u['id'] === $_SESSION['user_id']) {
        echo json_encode(['ok'=>true, 'user'=>['name'=>$u['name'],'address'=>$u['address']]]);
        exit;
    }
}
echo json_encode(['ok'=>false]);
