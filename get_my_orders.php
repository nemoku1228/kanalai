<?php
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_email'])) { echo json_encode(['ok'=>false,'orders'=>[]]); exit; }

$email = $_SESSION['user_email'];
$orders = file_exists('orders.json') ? json_decode(file_get_contents('orders.json'), true) : [];

$mine = array_filter($orders, fn($o) => isset($o['customer']['email']) && strtolower($o['customer']['email']) === $email);
$mine = array_reverse(array_values($mine));

echo json_encode(['ok'=>true, 'orders'=>$mine]);
