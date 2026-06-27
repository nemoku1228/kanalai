<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$file = __DIR__ . '/terminals.json';
$terminals = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$search = trim($_GET['search'] ?? '');
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 0; // 0 = be limito

// Pašalina lietuviškus diakritikos ženklus paieškai, kad "vilnius" rastų "Vilnius"
function normalizeLt($s) {
    $s = mb_strtolower($s, 'UTF-8');
    $map = [
        'ą'=>'a','č'=>'c','ę'=>'e','ė'=>'e','į'=>'i','š'=>'s','ų'=>'u','ū'=>'u','ž'=>'z'
    ];
    return strtr($s, $map);
}

if ($search !== '') {
    $q = normalizeLt($search);
    $terminals = array_values(array_filter($terminals, function($t) use ($q) {
        $haystack = normalizeLt(($t['name'] ?? '').' '.($t['city'] ?? '').' '.($t['address'] ?? ''));
        return mb_strpos($haystack, $q) !== false;
    }));
}

$total = count($terminals);
if ($limit > 0) {
    $terminals = array_slice($terminals, 0, $limit);
}

echo json_encode(['ok' => true, 'total' => $total, 'terminals' => $terminals], JSON_UNESCAPED_UNICODE);
