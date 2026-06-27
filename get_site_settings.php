<?php
/**
 * get_site_settings.php
 * Grąžina VIEŠAI prieinamus svetainės rodymo nustatymus (pavadinimas,
 * hero antraštė/poantraštė atitinkama kalba), kuriuos admin keičia per
 * Nustatymus. NIEKADA negrąžina jautrių duomenų (gmail_pass ir pan.).
 *
 * GET parametrai:
 *   ?lang=lt   (lt|en|ru|lv|et|fi, default lt)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$lang = $_GET['lang'] ?? 'lt';
if (!in_array($lang, ['lt','en','ru','lv','et','fi'])) $lang = 'lt';

$settingsFile = __DIR__ . '/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];

$defaultEyebrow = ['lt'=>'LIETUVOS EL. PARDUOTUVĖ','en'=>'LITHUANIAN ONLINE STORE','ru'=>'ЛИТОВСКИЙ ИНТЕРНЕТ-МАГАЗИН','lv'=>'LIETUVAS INTERNETA VEIKALS','et'=>'LEEDU E-POOD','fi'=>'LIETTUAN VERKKOKAUPPA'];
$defaultTitle   = ['lt'=>'Viskas, ko reikia, vienoje vietoje','en'=>'Everything you need, in one place','ru'=>'Всё, что вам нужно, в одном месте','lv'=>'Viss, kas nepieciešams, vienā vietā','et'=>'Kõik, mida vajate, ühes kohas','fi'=>'Kaikki tarvittava, yhdessä paikassa'];

$eyebrowMap = $settings['hero_eyebrow'] ?? $defaultEyebrow;
$titleMap   = $settings['hero_title'] ?? $defaultTitle;

echo json_encode([
    'ok' => true,
    'site_name' => $settings['site_name'] ?? 'market',
    'hero_eyebrow' => $eyebrowMap[$lang] ?? $eyebrowMap['lt'] ?? $defaultEyebrow['lt'],
    'hero_title' => $titleMap[$lang] ?? $titleMap['lt'] ?? $defaultTitle['lt'],
], JSON_UNESCAPED_UNICODE);
