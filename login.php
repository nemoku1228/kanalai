<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['email']) || empty($data['password'])) {
    echo json_encode(['ok'=>false,'msg'=>'Trūksta duomenų']); exit;
}

$email = strtolower(trim($data['email']));
$file  = __DIR__.'/users.json';
$users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

foreach ($users as $u) {
    if ($u['email'] === $email && password_verify($data['password'], $u['password'])) {
        // Patikra: jei laukas 'verified' egzistuoja IR yra false — paskyra nepatvirtinta.
        // Senesni vartotojai (be šio lauko) laikomi patvirtintais automatiškai.
        if (array_key_exists('verified', $u) && $u['verified'] === false) {
            echo json_encode(['ok'=>false, 'msg'=>'Patvirtinkite el. paštą — patikrinkite savo pašto dėžutę.', 'needs_verification'=>true]);
            exit;
        }
        session_start();
        $_SESSION['user_id']    = $u['id'];
        $_SESSION['user_email'] = $u['email'];
        $_SESSION['user_name']  = $u['name'];
        echo json_encode(['ok'=>true, 'name'=>$u['name']]);
        exit;
    }
}

echo json_encode(['ok'=>false,'msg'=>'Neteisingas el. paštas arba slaptažodis']);
