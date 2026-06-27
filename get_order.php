<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if (!$id) { echo json_encode(['ok' => false]); exit; }

$file = 'orders.json';
if (!file_exists($file)) { echo json_encode(['ok' => false]); exit; }

$orders = json_decode(file_get_contents($file), true) ?? [];

foreach ($orders as $order) {
    if ($order['id'] === $id) {
        // Grąžiname tik reikalingus laukus klientui (ne visą info)
        echo json_encode([
            'ok' => true,
            'order' => [
                'id'       => $order['id'],
                'date'     => $order['date'],
                'status'   => $order['status'],
                'tracking' => $order['tracking'],
                'delivery' => $order['delivery'],
                'total'    => $order['total'],
            ]
        ]);
        exit;
    }
}

echo json_encode(['ok' => false]);
