<?php
/**
 * get_categories.php
 * Grąžina pilną kategorijų medį (tėvinė + pogrupiai) JSON formatu,
 * kad svetainė galėtų sudėliuoti naršymo meniu.
 *
 * Taip pat papildomai grąžina kiekvienos kategorijos prekių kiekį
 * (kad svetainėje būtų galima rodyti pvz. "Baldai (12)").
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$categoriesFile = __DIR__ . '/categories.json';
$productsFile   = __DIR__ . '/products.json';

$categories = file_exists($categoriesFile)
    ? json_decode(file_get_contents($categoriesFile), true)
    : [];

$products = file_exists($productsFile)
    ? json_decode(file_get_contents($productsFile), true)
    : [];

// Skaičiuojame kiek aktyvių prekių yra kiekviename pogrupyje
$counts = [];
foreach ($products as $p) {
    if (($p['active'] ?? 1) != 1) continue;
    $parent = $p['category_parent'] ?? 'Nepriskirta';
    $sub    = $p['category_sub'] ?? 'Nepriskirta';
    $key = $parent . '|||' . $sub;
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}

$result = [];
foreach ($categories as $cat) {
    $parentName = $cat['name'];
    $children = [];
    $parentTotal = 0;

    foreach (($cat['children'] ?? []) as $child) {
        $key = $parentName . '|||' . $child;
        $cnt = $counts[$key] ?? 0;
        $parentTotal += $cnt;
        $children[] = ['name' => $child, 'count' => $cnt];
    }

    // Jei kategorija be pogrupių (children tuščias), skaičiuojame pagal save
    if (empty($cat['children'])) {
        $key = $parentName . '|||' . $parentName;
        $parentTotal = $counts[$key] ?? 0;
    }

    $result[] = [
        'name' => $parentName,
        'count' => $parentTotal,
        'children' => $children,
    ];
}

echo json_encode(['ok' => true, 'categories' => $result], JSON_UNESCAPED_UNICODE);
