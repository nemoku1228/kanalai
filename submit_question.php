<?php
/**
 * submit_question.php
 * Klientas (prisijungęs arba neprisijungęs) užduoda klausimą apie konkrečią
 * prekę iš product.html puslapio. Klausimas saugomas questions.json faile,
 * admin panelėje matomas „Klausimai" skiltyje su statusu Nauja/Atsakyta.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Trūksta duomenų']); exit; }

$productId   = trim($data['product_id'] ?? '');
$productName = trim($data['product_name'] ?? '');
$name        = trim($data['name'] ?? '');
$email       = trim($data['email'] ?? '');
$message     = trim($data['message'] ?? '');

if (!$productId || !$name || !$email || !$message) {
    echo json_encode(['ok'=>false,'msg'=>'Užpildykite visus laukus']); exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false,'msg'=>'Neteisingas el. pašto adresas']); exit;
}

$file = __DIR__ . '/questions.json';
$questions = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($clientIp, ',') !== false) $clientIp = trim(explode(',', $clientIp)[0]);

$question = [
    'id'           => 'Q-' . substr(md5(microtime()), 0, 8),
    'product_id'   => $productId,
    'product_name' => $productName,
    'name'         => $name,
    'email'        => $email,
    'message'      => $message,
    'answer'       => '',
    'status'       => 'Nauja', // Nauja | Atsakyta
    'created_at'   => date('Y-m-d H:i:s'),
    'answered_at'  => null,
    'ip'           => $clientIp,
];

$questions[] = $question;
file_put_contents($file, json_encode($questions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

echo json_encode(['ok'=>true, 'id'=>$question['id']]);
