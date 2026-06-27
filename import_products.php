<?php
/**
 * import_products.php
 *
 * NAUDOJIMAS:
 *   1. Per FTP įkelk visą "ready_for_upload" aplanką (kurį sukuria import_runner.py)
 *      į savo svetainės šaknį, pvz: import_queue/SKU/product.json + images/
 *   2. Atsidaryk šitą puslapį naršyklėje, prisijungęs prie admin (naudoja tą pačią sesiją).
 *   3. Skriptas:
 *        - peržiūri visus import_queue katalogo product.json failus
 *        - kelia nuotraukas į uploads/products/
 *        - prideda naują įrašą į products.json (su pavadinimu/aprašymu KIEKVIENA
 *          iš 4 kalbų: lt, lv, et, fi)
 *        - po sėkmingo importo perkelia tą produkto aplanką į import_queue_done katalogą
 *   4. Po importo gali ištrinti šį failą arba apsaugoti slaptažodžiu (žemiau).
 */

session_start();
if (!isset($_SESSION['admin'])) {
    die('Prieiga uždrausta. Prisijunk per admin.php pirmiausia tame pačiame naršyklės lange.');
}

$QUEUE_DIR  = __DIR__ . '/import_queue';
$DONE_DIR   = __DIR__ . '/import_queue_done';
$UPLOAD_DIR = __DIR__ . '/uploads/products';
$PRODUCTS_FILE = __DIR__ . '/products.json';

if (!is_dir($QUEUE_DIR))  mkdir($QUEUE_DIR, 0755, true);
if (!is_dir($DONE_DIR))   mkdir($DONE_DIR, 0755, true);
if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);

function loadProducts($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?? [];
}
function saveProducts($file, $products) {
    file_put_contents($file, json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

$results = [];

if (isset($_GET['run'])) {
    $products = loadProducts($PRODUCTS_FILE);
    $folders = array_filter(glob($QUEUE_DIR . '/*'), 'is_dir');

    foreach ($folders as $folder) {
        $sku = basename($folder);
        $jsonPath = $folder . '/product.json';

        if (!file_exists($jsonPath)) {
            $results[] = ['sku' => $sku, 'ok' => false, 'msg' => 'product.json nerastas'];
            continue;
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) {
            $results[] = ['sku' => $sku, 'ok' => false, 'msg' => 'Neteisingas JSON'];
            continue;
        }

        // Kelti nuotraukas
        $movedImages = [];
        $imagesFolder = $folder . '/images';
        if (is_dir($imagesFolder)) {
            foreach (glob($imagesFolder . '/*') as $imgPath) {
                $imgName = basename($imgPath);
                $destPath = $UPLOAD_DIR . '/' . $imgName;
                if (copy($imgPath, $destPath)) {
                    $movedImages[] = $imgName;
                }
            }
        }

        // Sudaryti naują produkto įrašą su daugiakalbiais laukais
        $t = $data['translations'] ?? [];
        $newProduct = [
            'id'       => 'PRD-' . substr(md5($sku . microtime()), 0, 6),
            'sku'      => $sku,
            'oem'      => $sku,
            'price'    => (float)($data['price'] ?? 0),
            'stock'    => 0,
            'category_parent' => $data['category_parent'] ?? 'Nepriskirta',
            'category_sub'    => $data['category_sub'] ?? 'Nepriskirta',
            'active'   => 1,
            'images'   => $movedImages,
            'created'  => date('Y-m-d H:i:s'),
            'source_title_pl' => $data['original_title_pl'] ?? '',
            // Pagrindinis "name"/"desc" laukas (admin lentelei) — naudojame lietuvių kalbą
            'name'     => $t['lt']['name'] ?? $sku,
            'desc'     => $t['lt']['description'] ?? '',
            // Visos kalbos atskirai — naudoja svetainė rodydama pasirinkta kalba
            'i18n' => [
                'lt' => ['name' => $t['lt']['name'] ?? '', 'description' => $t['lt']['description'] ?? ''],
                'lv' => ['name' => $t['lv']['name'] ?? '', 'description' => $t['lv']['description'] ?? ''],
                'et' => ['name' => $t['et']['name'] ?? '', 'description' => $t['et']['description'] ?? ''],
                'fi' => ['name' => $t['fi']['name'] ?? '', 'description' => $t['fi']['description'] ?? ''],
                'en' => ['name' => $t['en']['name'] ?? '', 'description' => $t['en']['description'] ?? ''],
                'ru' => ['name' => $t['ru']['name'] ?? '', 'description' => $t['ru']['description'] ?? ''],
            ],
        ];

        $products[] = $newProduct;

        // Perkelti aplanką į done
        $destFolder = $DONE_DIR . '/' . $sku;
        rename($folder, $destFolder);

        $results[] = [
            'sku' => $sku, 'ok' => true,
            'msg' => "Importuota: {$newProduct['name']} (" . count($movedImages) . " nuotr.)"
        ];
    }

    saveProducts($PRODUCTS_FILE, $products);
}

// Peržiūrai — kiek laukia eilėje
$pendingFolders = array_filter(glob($QUEUE_DIR . '/*'), 'is_dir');
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<title>Prekių importas</title>
<style>
body{font-family:Arial,sans-serif;background:#f1f5f9;padding:30px;color:#0f172a}
.box{background:white;border-radius:12px;padding:24px;max-width:800px;margin:0 auto;box-shadow:0 1px 3px rgba(0,0,0,.1)}
h1{font-size:20px;margin-bottom:16px}
.pending{background:#fef3c7;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:14px}
.btn{background:#2563eb;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;display:inline-block}
.result{padding:10px 14px;border-radius:8px;margin-bottom:8px;font-size:13px}
.ok{background:#dcfce7;color:#166534}
.fail{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
<div class="box">
<h1>Prekių importas iš import_queue/</h1>
<div class="pending">Laukia importo: <strong><?= count($pendingFolders) ?></strong> prekių aplankų</div>

<?php if (!empty($results)): ?>
<h3>Rezultatai:</h3>
<?php foreach ($results as $r): ?>
<div class="result <?= $r['ok'] ? 'ok' : 'fail' ?>">
    <strong><?= htmlspecialchars($r['sku']) ?></strong> — <?= htmlspecialchars($r['msg']) ?>
</div>
<?php endforeach; ?>
<p><a href="admin.php" class="btn">Eiti į admin panelę</a></p>
<?php else: ?>
<a href="?run=1" class="btn">Paleisti importą</a>
<?php endif; ?>
</div>
</body>
</html>
