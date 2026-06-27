<?php
/**
 * download_invoice.php
 * Leidžia prisijungusiam klientui atsisiųsti SAVO sąskaitos PDF.
 * Saugumas: tikrina, kad užsakymo email sutampa su prisijungusio
 * vartotojo sesijos email — apsaugo nuo svetimų sąskaitų atsisiuntimo.
 *
 * GET parametrai:
 *   ?id=ORD-XXXXXX        (užsakymo numeris)
 *   ?type=credit          (pasirinktinai — atsisiunčia kreditinę sąskaitą)
 */

session_start();

if (!isset($_SESSION['user_email'])) {
    http_response_code(403);
    echo 'Prisijunkite, kad galėtumėte atsisiųsti sąskaitą.';
    exit;
}

$orderId = trim($_GET['id'] ?? '');
$type = $_GET['type'] ?? 'invoice';

if (!$orderId) {
    http_response_code(400);
    echo 'Trūksta užsakymo numerio.';
    exit;
}

$ordersFile = __DIR__ . '/orders.json';
$orders = file_exists($ordersFile) ? json_decode(file_get_contents($ordersFile), true) : [];

$order = null;
foreach ($orders as $o) {
    if ($o['id'] === $orderId) { $order = $o; break; }
}

if (!$order) {
    http_response_code(404);
    echo 'Užsakymas nerastas.';
    exit;
}

// Saugumo patikra — užsakymo email turi sutapti su sesijos email
$sessionEmail = strtolower(trim($_SESSION['user_email']));
$orderEmail = strtolower(trim($order['customer']['email'] ?? ''));
if ($sessionEmail !== $orderEmail) {
    http_response_code(403);
    echo 'Neturite teisės atsisiųsti šios sąskaitos.';
    exit;
}

$filename = $type === 'credit' ? 'credit_invoice_' . $orderId . '.pdf' : 'invoice_' . $orderId . '.pdf';
$filePath = __DIR__ . '/invoices/' . $filename;

if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'Sąskaitos failas nerastas.';
    exit;
}

$downloadName = ($type === 'credit' ? 'Kreditine_saskaita_' : 'PVM_saskaita_') . $orderId . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
