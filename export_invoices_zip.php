<?php
/**
 * export_invoices_zip.php
 * Pakuoja PVM sąskaitų (arba kreditinių sąskaitų) PDF failus į ZIP
 * archyvą, filtruojant pagal datos diapazoną.
 *
 * GET parametrai:
 *   ?from=2026-01-01   (pasirinktinai)
 *   ?to=2026-12-31     (pasirinktinai)
 *   ?type=invoice       (invoice | credit)
 */

session_start();
if (!isset($_SESSION['admin'])) { http_response_code(403); echo 'Neturite teisės.'; exit; }

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$type = $_GET['type'] ?? 'invoice'; // invoice | credit

$orders = file_exists(__DIR__.'/orders.json') ? json_decode(file_get_contents(__DIR__.'/orders.json'), true) : [];

function withinRangeInv($dateStr, $from, $to) {
    $date = substr($dateStr, 0, 10);
    if ($from && $date < $from) return false;
    if ($to && $date > $to) return false;
    return true;
}

$filtered = array_values(array_filter($orders, function($o) use ($from, $to, $type) {
    if (!withinRangeInv($o['date'] ?? '', $from, $to)) return false;
    if ($type === 'credit') {
        // Kreditinė egzistuoja tik atšauktiems užsakymams su sąskaita
        return $o['status'] === 'Cancelled' && !empty($o['invoice']);
    }
    return !empty($o['invoice']);
}));

if (empty($filtered)) {
    http_response_code(404);
    echo 'Pasirinktu laikotarpiu sąskaitų nerasta.';
    exit;
}

$invoicesDir = __DIR__ . '/invoices';
$tmpZip = tempnam(sys_get_temp_dir(), 'inv_zip');
$zip = new ZipArchive();
$zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$addedCount = 0;
foreach ($filtered as $o) {
    $oid = $o['id'] ?? '';
    if (!$oid) continue;

    $filename = $type === 'credit' ? "credit_invoice_{$oid}.pdf" : "invoice_{$oid}.pdf";
    $filePath = $invoicesDir . '/' . $filename;

    if (file_exists($filePath)) {
        $displayName = $type === 'credit' ? "Kreditine_saskaita_{$oid}.pdf" : "PVM_saskaita_{$oid}.pdf";
        $zip->addFile($filePath, $displayName);
        $addedCount++;
    }
}

$zip->close();

if ($addedCount === 0) {
    unlink($tmpZip);
    http_response_code(404);
    $msg = $type === 'credit'
        ? 'Pasirinktu laikotarpiu kreditinių sąskaitų PDF failų nerasta (galimai jos nebuvo sugeneruotos).'
        : 'Pasirinktu laikotarpiu PVM sąskaitų PDF failų nerasta.';
    echo $msg;
    exit;
}

$zipName = ($type === 'credit' ? 'kreditines_saskaitos_' : 'pvm_saskaitos_') . date('Y-m-d') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tmpZip));
readfile($tmpZip);
unlink($tmpZip);
