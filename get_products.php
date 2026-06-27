<?php
/**
 * get_products.php
 * Grąžina prekes su filtravimu pagal kategoriją (tėvinė/pogrupis) ir
 * pasirinktą rodymo kalbą (lt/lv/et/fi/en/ru).
 *
 * Kaina skaičiuojama GYVAI pagal settings.json antkainio/nuolaidos %:
 *   bazinė_kaina = produkto kaina (originali, iš Allegro ar admin)
 *   su_antkainiu = bazinė_kaina * (1 + markup% / 100)
 *   rodoma_kaina (su nuolaida) = su_antkainiu
 *   senoji_kaina (be nuolaidos, perbraukta) = su_antkainiu * (1 + discount% / 100)
 *
 * Pvz: bazinė 100€, markup 0%, discount 20% ->
 *      rodoma 100€, senoji (perbraukta) 120€
 *
 * GET parametrai:
 *   ?category_parent=Baldai
 *   ?category_sub=Svetainės+baldai
 *   ?lang=lt   (default lt)
 *   ?search=fotelis
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$productsFile = __DIR__ . '/products.json';
$products = file_exists($productsFile)
    ? json_decode(file_get_contents($productsFile), true)
    : [];

$settingsFile = __DIR__ . '/settings.json';
$settings = file_exists($settingsFile)
    ? json_decode(file_get_contents($settingsFile), true)
    : [];

$defaultMarkup   = (float)($settings['default_markup_percent'] ?? 0);
$defaultDiscount = (float)($settings['default_discount_percent'] ?? 0);
$categoryMarkup  = $settings['category_markup'] ?? [];
$priceRangeMarkup = $settings['price_range_markup'] ?? [];

/**
 * Nustato taikomą antkainio procentą pagal prioritetą:
 *   1. Kainos diapazono antkainis (jei bazinė kaina patenka į intervalą)
 *   2. Kategorijos antkainis (jei nustatytas konkrečiai kategorijai)
 *   3. Bendras antkainis (numatytasis visoms prekėms)
 */
function resolveMarkupPercent($basePrice, $categoryParent, $defaultMarkup, $categoryMarkup, $priceRangeMarkup) {
    foreach ($priceRangeMarkup as $range) {
        $min = (float)($range['min'] ?? 0);
        $max = (float)($range['max'] ?? PHP_FLOAT_MAX);
        if ($basePrice >= $min && $basePrice < $max) {
            return (float)($range['markup'] ?? $defaultMarkup);
        }
    }
    if (isset($categoryMarkup[$categoryParent])) {
        return (float)$categoryMarkup[$categoryParent];
    }
    return $defaultMarkup;
}

function calcPrices($basePrice, $categoryParent, $defaultMarkup, $defaultDiscount, $categoryMarkup, $priceRangeMarkup) {
    $markup = resolveMarkupPercent($basePrice, $categoryParent, $defaultMarkup, $categoryMarkup, $priceRangeMarkup);
    $priceWithMarkup = $basePrice * (1 + $markup / 100);
    $oldPrice = $defaultDiscount > 0 ? round($priceWithMarkup * (1 + $defaultDiscount / 100), 2) : null;
    return [
        'price' => round($priceWithMarkup, 2),
        'old_price' => $oldPrice,
        'discount_percent' => $defaultDiscount > 0 ? $defaultDiscount : null,
    ];
}

$lang = $_GET['lang'] ?? 'lt';
if (!in_array($lang, ['lt','lv','et','fi','en','ru'])) $lang = 'lt';

$filterParent = $_GET['category_parent'] ?? null;
$filterSub    = $_GET['category_sub'] ?? null;
$search       = trim($_GET['search'] ?? '');

$result = [];
foreach ($products as $p) {
    if (($p['active'] ?? 1) != 1) continue;

    if ($filterParent && ($p['category_parent'] ?? '') !== $filterParent) continue;
    if ($filterSub && ($p['category_sub'] ?? '') !== $filterSub) continue;

    $i18n = $p['i18n'][$lang] ?? null;
    $name = $i18n['name'] ?? ($p['name'] ?? $p['sku'] ?? '');
    $desc = $i18n['description'] ?? ($p['desc'] ?? '');

    if ($search) {
        $haystack = mb_strtolower($name . ' ' . ($p['sku'] ?? ''));
        if (mb_strpos($haystack, mb_strtolower($search)) === false) continue;
    }

    $basePrice = (float)($p['price'] ?? 0);
    $categoryParent = $p['category_parent'] ?? '';
    $prices = calcPrices($basePrice, $categoryParent, $defaultMarkup, $defaultDiscount, $categoryMarkup, $priceRangeMarkup);

    $result[] = [
        'id' => $p['id'] ?? $p['sku'] ?? '',
        'sku' => $p['sku'] ?? '',
        'display_code' => $p['display_code'] ?? '',
        'name' => $name,
        'description' => $desc,
        'price' => $prices['price'],
        'old_price' => $prices['old_price'],
        'discount_percent' => $prices['discount_percent'],
        'stock' => (int)($p['stock'] ?? 0),
        'category_parent' => $p['category_parent'] ?? '',
        'category_sub' => $p['category_sub'] ?? '',
        'images' => $p['images'] ?? [],
        'main_image' => !empty($p['images'][0]) ? $p['images'][0] : null,
    ];
}

echo json_encode(['ok' => true, 'lang' => $lang, 'count' => count($result), 'products' => $result], JSON_UNESCAPED_UNICODE);
