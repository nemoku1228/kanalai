<?php
/**
 * manage_addresses.php
 * Leidžia prisijungusiam klientui valdyti kelis išsaugotus adresus
 * (pridėti, ištrinti, sąrašyti). Naudojama checkout metu pasirinkti
 * jau išsaugotą adresą, arba paskyroje juos tvarkyti.
 *
 * Veiksmai (POST JSON body su 'action' lauku):
 *   {action: 'list'}
 *   {action: 'add', label: 'Namai', name, surname, city, street, zipcode, phone}
 *   {action: 'delete', address_id: 'ADDR-xxxx'}
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_email'])) {
    echo json_encode(['ok'=>false, 'msg'=>'Neprisijungta']); exit;
}

$email = $_SESSION['user_email'];
$usersFile = __DIR__ . '/users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'list';

$userIndex = null;
foreach ($users as $i => $u) {
    if ($u['email'] === $email) { $userIndex = $i; break; }
}

if ($userIndex === null) {
    echo json_encode(['ok'=>false, 'msg'=>'Vartotojas nerastas']); exit;
}

// Migracija: jei vartotojas turi tik senąjį 'address' lauką, paverčiam į 'addresses' masyvą
if (!isset($users[$userIndex]['addresses'])) {
    $users[$userIndex]['addresses'] = [];
    $oldAddr = $users[$userIndex]['address'] ?? null;
    if ($oldAddr && !empty($oldAddr['street'])) {
        $users[$userIndex]['addresses'][] = [
            'id' => 'ADDR-' . substr(md5('legacy'.$email), 0, 8),
            'label' => 'Pagrindinis',
            'name' => $users[$userIndex]['name'] ?? '',
            'surname' => '',
            'city' => $oldAddr['city'] ?? '',
            'street' => $oldAddr['street'] ?? '',
            'zipcode' => $oldAddr['zipcode'] ?? '',
            'phone' => '',
        ];
    }
}

if ($action === 'list') {
    echo json_encode(['ok'=>true, 'addresses'=>$users[$userIndex]['addresses']]);
    exit;
}

if ($action === 'add') {
    $label = trim($data['label'] ?? 'Adresas');
    $name = trim($data['name'] ?? '');
    $surname = trim($data['surname'] ?? '');
    $city = trim($data['city'] ?? '');
    $street = trim($data['street'] ?? '');
    $zipcode = trim($data['zipcode'] ?? '');
    $phone = trim($data['phone'] ?? '');

    if (!$name || !$city || !$street || !$zipcode) {
        echo json_encode(['ok'=>false, 'msg'=>'Užpildykite visus privalomus laukus']); exit;
    }

    $newAddress = [
        'id' => 'ADDR-' . substr(md5($email . microtime()), 0, 8),
        'label' => $label ?: 'Adresas',
        'name' => $name,
        'surname' => $surname,
        'city' => $city,
        'street' => $street,
        'zipcode' => $zipcode,
        'phone' => $phone,
    ];

    $users[$userIndex]['addresses'][] = $newAddress;
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

    echo json_encode(['ok'=>true, 'address'=>$newAddress]);
    exit;
}

if ($action === 'delete') {
    $addressId = $data['address_id'] ?? '';
    $users[$userIndex]['addresses'] = array_values(array_filter(
        $users[$userIndex]['addresses'],
        fn($a) => $a['id'] !== $addressId
    ));
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo json_encode(['ok'=>true]);
    exit;
}

echo json_encode(['ok'=>false, 'msg'=>'Nežinomas veiksmas']);
