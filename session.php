<?php
header('Content-Type: application/json');
session_start();
if (isset($_SESSION['user_id'])) {
    echo json_encode(['ok'=>true, 'name'=>$_SESSION['user_name'], 'email'=>$_SESSION['user_email'], 'id'=>$_SESSION['user_id']]);
} else {
    echo json_encode(['ok'=>false]);
}
