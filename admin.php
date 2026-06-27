<?php
session_start();

// ── NUSTATYMAI ───────────────────────────────────────────
$ADMIN_PASS = 'admin123'; // <-- PAKEISK

// Login
if (isset($_POST['password'])) {
    if ($_POST['password'] === $ADMIN_PASS) { $_SESSION['admin'] = true; header('Location: admin.php'); exit; }
    else $loginError = 'Neteisingas slaptažodis';
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

// Svetainės pavadinimas reikalingas net prisijungimo ekrane — užkraunam anksti
$loginSiteName = 'market';
if (file_exists(__DIR__.'/settings.json')) {
    $loginSettings = json_decode(file_get_contents(__DIR__.'/settings.json'), true);
    if (!empty($loginSettings['site_name'])) $loginSiteName = $loginSettings['site_name'];
}

if (!isset($_SESSION['admin'])) goto SHOW_LOGIN;

// ── DATA HELPERS ─────────────────────────────────────────
function loadOrders() {
    if (!file_exists('orders.json')) return [];
    return json_decode(file_get_contents('orders.json'), true) ?? [];
}
function saveOrders($orders) {
    file_put_contents('orders.json', json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function loadUsers() {
    if (!file_exists('users.json')) return [];
    return json_decode(file_get_contents('users.json'), true) ?? [];
}
function loadProducts() {
    if (!file_exists('products.json')) return [];
    return json_decode(file_get_contents('products.json'), true) ?? [];
}
function saveProducts($products) {
    file_put_contents('products.json', json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function loadSettings() {
    if (!file_exists('settings.json')) return ['gmail_user'=>'','gmail_pass'=>'','admin_email'=>'','site_url'=>'','site_name'=>'Pneumatinės Pagalvės'];
    return json_decode(file_get_contents('settings.json'), true) ?? [];
}
function saveSettings($s) {
    file_put_contents('settings.json', json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function loadQuestions() {
    if (!file_exists('questions.json')) return [];
    return json_decode(file_get_contents('questions.json'), true) ?? [];
}
function saveQuestions($q) {
    file_put_contents('questions.json', json_encode(array_values($q), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function generateUniqueDisplayCode($existingProducts) {
    $existing = array_column($existingProducts, 'display_code');
    do {
        $code = (string)random_int(100000, 999999);
    } while (in_array($code, $existing));
    return $code;
}

// ── AJAX HANDLERS ─────────────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? '';

    if ($action === 'upload_import_json') {
        $products = loadProducts();
        $results = [];

        if (empty($_FILES['json_files'])) {
            echo json_encode(['ok'=>false, 'msg'=>'Failų nepasirinkta']); exit;
        }

        $files = $_FILES['json_files'];
        $count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 0;

        for ($i = 0; $i < $count; $i++) {
            $tmpPath = $files['tmp_name'][$i];
            $origName = $files['name'][$i];

            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $results[] = ['sku' => $origName, 'ok' => false, 'msg' => 'Įkėlimo klaida'];
                continue;
            }

            $raw = file_get_contents($tmpPath);
            $data = json_decode($raw, true);
            if (!$data) {
                $results[] = ['sku' => $origName, 'ok' => false, 'msg' => 'Neteisingas JSON'];
                continue;
            }

            $sku = $data['sku'] ?? pathinfo($origName, PATHINFO_FILENAME);

            // Tikrinam, ar šis SKU jau importuotas (apsauga nuo dublikatų)
            $alreadyExists = false;
            foreach ($products as $p) {
                if (($p['sku'] ?? '') === $sku) { $alreadyExists = true; break; }
            }
            if ($alreadyExists) {
                $results[] = ['sku' => $sku, 'ok' => false, 'msg' => 'Šis SKU jau importuotas anksčiau'];
                continue;
            }

            $t = $data['translations'] ?? [];
            // Nuotraukos — saugomi tiesiogiai Allegro URL, NEKOPIJUOJAMI į serverį
            $imageUrls = $data['image_urls'] ?? $data['images'] ?? [];

            $newProduct = [
                'id'       => 'PRD-' . substr(md5($sku . microtime()), 0, 6),
                'display_code' => generateUniqueDisplayCode($products),
                'sku'      => $sku,
                'oem'      => $sku,
                'price'    => (float)($data['price'] ?? 0),
                'stock'    => 0,
                'category_parent' => $data['category_parent'] ?? 'Nepriskirta',
                'category_sub'    => $data['category_sub'] ?? 'Nepriskirta',
                'active'   => 1,
                'images'   => $imageUrls,       // pilni Allegro URL, NE lokalūs failai
                'image_source' => 'external',   // žyma — frontend žino, kad tai URL, ne uploads/products/
                'created'  => date('Y-m-d H:i:s'),
                'source_title_pl' => $data['original_title_pl'] ?? '',
                'name'     => $t['lt']['name'] ?? $sku,
                'desc'     => $t['lt']['description'] ?? '',
                'i18n' => [
                    'lt' => ['name'=>$t['lt']['name']??'', 'description'=>$t['lt']['description']??''],
                    'lv' => ['name'=>$t['lv']['name']??'', 'description'=>$t['lv']['description']??''],
                    'et' => ['name'=>$t['et']['name']??'', 'description'=>$t['et']['description']??''],
                    'fi' => ['name'=>$t['fi']['name']??'', 'description'=>$t['fi']['description']??''],
                    'en' => ['name'=>$t['en']['name']??'', 'description'=>$t['en']['description']??''],
                    'ru' => ['name'=>$t['ru']['name']??'', 'description'=>$t['ru']['description']??''],
                ],
            ];
            $products[] = $newProduct;
            $results[] = ['sku' => $sku, 'ok' => true, 'msg' => "Importuota: {$newProduct['name']} (".count($imageUrls)." nuotr. URL)"];
        }

        saveProducts($products);
        echo json_encode(['ok'=>true, 'results'=>$results]); exit;
    }

    if ($action === 'update_order') {
        $data = json_decode(file_get_contents('php://input'), true);
        $orders = loadOrders();
        $updatedOrder = null; $statusChanged = false; $trackingAdded = false; $notesChanged = false;

        foreach ($orders as &$o) {
            if ($o['id'] === $data['id']) {
                if (isset($data['status']) && $data['status'] !== $o['status']) { $o['status'] = $data['status']; $statusChanged = true; }
                if (isset($data['tracking']) && $data['tracking'] !== ($o['tracking'] ?? '')) { $o['tracking'] = $data['tracking']; $trackingAdded = !empty($data['tracking']); }
                if (isset($data['notes']) && $data['notes'] !== ($o['notes'] ?? '')) { $o['notes'] = $data['notes']; $notesChanged = !empty($data['notes']); }
                $updatedOrder = $o;
            }
        }
        unset($o);
        saveOrders($orders);

        // Atšaukus užsakymą — automatiškai išimti prekes iš prekybos (stock = 0)
        if ($statusChanged && $updatedOrder && $updatedOrder['status'] === 'Cancelled') {
            $prodList = loadProducts();
            $stockChanged = false;
            foreach ($prodList as &$pp) {
                foreach (($updatedOrder['cart'] ?? []) as $ci) {
                    $matchId  = isset($ci['id']) && ($pp['id'] ?? null) === $ci['id'];
                    $matchSku = !empty($ci['sku']) && ($pp['sku'] ?? '') === $ci['sku'];
                    if (($matchId || $matchSku) && (int)($pp['stock'] ?? 0) !== 0) { $pp['stock'] = 0; $stockChanged = true; }
                }
            }
            unset($pp);
            if ($stockChanged) saveProducts($prodList);
        }
        if ($updatedOrder && !empty($updatedOrder['customer']['email'])) {
            try {
                $emailCfg = loadSettings();
                $cGmailUser = $emailCfg['gmail_user'] ?? '';
                $cGmailPass = $emailCfg['gmail_pass'] ?? '';
                $cSiteUrl   = $emailCfg['site_url'] ?? '';
                $cSiteName  = $emailCfg['site_name'] ?? 'market';
                $custEmail  = $updatedOrder['customer']['email'];
                $custName   = $updatedOrder['customer']['name'] ?? '';
                $oid        = $updatedOrder['id'];

                if (!empty($cGmailUser) && !empty($cGmailPass)) {
                    $phpmailerPath = __DIR__.'/PHPMailer/src/';
                    if (file_exists($phpmailerPath.'Exception.php') && file_exists($phpmailerPath.'PHPMailer.php') && file_exists($phpmailerPath.'SMTP.php')) {
                        require_once $phpmailerPath.'Exception.php';
                        require_once $phpmailerPath.'PHPMailer.php';
                        require_once $phpmailerPath.'SMTP.php';

                        $sendOrderEmail = function($subject, $html, $attachPath = null, $attachName = null) use ($cGmailUser, $cGmailPass, $cSiteName, $custEmail) {
                            try {
                                $m = new PHPMailer\PHPMailer\PHPMailer(true);
                                $m->isSMTP();
                                $m->Host = 'smtp.gmail.com';
                                $m->SMTPAuth = true;
                                $m->Username = $cGmailUser;
                                $m->Password = $cGmailPass;
                                $m->SMTPSecure = 'tls';
                                $m->Port = 587;
                                $m->CharSet = 'UTF-8';
                                $m->setFrom($cGmailUser, $cSiteName);
                                $m->addReplyTo($cGmailUser, $cSiteName);
                                $m->addAddress($custEmail);
                                $m->isHTML(true);
                                $m->Subject = $subject;
                                $m->Body = $html;
                                $m->AltBody = trim(strip_tags(preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html)));
                                if ($attachPath && file_exists($attachPath)) $m->addAttachment($attachPath, $attachName ?? basename($attachPath));
                                $m->send();
                            } catch (\Throwable $e) {
                                @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] admin update_order email failed: '.$e->getMessage()."\n", FILE_APPEND);
                            }
                        };

                        $statusLabelsLT = ['Submitted'=>'Pateiktas','Confirmed'=>'Patvirtintas','Processed'=>'Išsiųstas','Completed'=>'Pristatytas','Cancelled'=>'Atšauktas'];

                        // 1) Sekimo numeris pridėtas/pakeistas
                        if ($trackingAdded) {
                            $trk = $updatedOrder['tracking'];
                            $html = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><div style='background:#1e293b;color:white;padding:20px 30px;border-radius:10px'><h1 style='margin:0'>Jūsų siunta išsiųsta!</h1><p style='color:#93c5fd'>{$oid}</p></div><div style='background:white;padding:20px 30px;border:1px solid #e2e8f0'><p>Sveiki, <strong>{$custName}</strong>!</p><div style='background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px;margin:15px 0;text-align:center'><p style='margin:0 0 5px;font-size:13px;color:#166534;font-weight:bold'>SEKIMO NUMERIS</p><p style='margin:0;font-size:24px;font-weight:bold;color:#15803d;font-family:monospace'>{$trk}</p></div></div><div style='text-align:center;padding:15px 30px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:0 0 10px 10px'><a href='".rtrim($cSiteUrl,'/')."/track.html' style='background:#1e293b;color:white;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:bold'>Sekti siuntą</a></div></div>";
                            $sendOrderEmail("Jūsų siunta išsiųsta - {$oid}", $html);
                        }

                        // 2) Bet kokio statuso pasikeitimas (bendras pranešimas su žmogui suprantamu statusu)
                        if ($statusChanged) {
                            $statusLabel = $statusLabelsLT[$updatedOrder['status']] ?? $updatedOrder['status'];
                            $color = $updatedOrder['status']==='Cancelled' ? '#dc2626' : ($updatedOrder['status']==='Completed' ? '#16a34a' : '#2563eb');

                            $creditPdfPath = null;
                            $creditNoteHtml = '';
                            if ($updatedOrder['status'] === 'Cancelled' && !empty($updatedOrder['invoice'])) {
                                try {
                                    require_once __DIR__.'/generate_invoice.php';
                                    $creditResult = generateCreditInvoicePdf($updatedOrder);
                                    if ($creditResult) {
                                        $creditPdfPath = $creditResult['path'];
                                        $creditNoteHtml = "<p style='font-size:13px;color:#64748b;margin-top:10px'>Kreditinė sąskaita-faktūra pridėta prie šio laiško.</p>";

                                        // Išsaugom kreditinės sąskaitos numerį atgal į orders.json
                                        $ordersForCreditUpdate = loadOrders();
                                        foreach ($ordersForCreditUpdate as &$ocu) {
                                            if ($ocu['id'] === $updatedOrder['id']) { $ocu['credit_invoice_number'] = $creditResult['invoice_number']; break; }
                                        }
                                        unset($ocu);
                                        saveOrders($ordersForCreditUpdate);
                                    }
                                } catch (\Throwable $e) {
                                    @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] Credit invoice generation failed: '.$e->getMessage()."\n", FILE_APPEND);
                                }
                            }

                            $notesHtml = (!empty($updatedOrder['notes'])) ? "<div style='background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:14px;margin-top:14px'><p style='margin:0 0 4px;font-size:12px;font-weight:bold;color:#92400e'>PASTABA</p><p style='margin:0;font-size:13px;color:#78350f'>".nl2br(htmlspecialchars($updatedOrder['notes']))."</p></div>" : '';

                            $html = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><div style='background:{$color};color:white;padding:20px 30px;border-radius:10px 10px 0 0'><h1 style='margin:0;font-size:20px'>Užsakymo statusas pasikeitė</h1><p style='margin:5px 0 0;opacity:.85'>{$oid}</p></div><div style='background:white;padding:20px 30px;border:1px solid #e2e8f0;border-radius:0 0 10px 10px'><p>Sveiki, <strong>{$custName}</strong>!</p><p>Jūsų užsakymo naujas statusas: <strong style='color:{$color}'>{$statusLabel}</strong></p>{$notesHtml}{$creditNoteHtml}</div></div>";
                            $sendOrderEmail("Užsakymo statusas: {$statusLabel} - {$oid}", $html, $creditPdfPath, $creditPdfPath ? 'Kreditine_saskaita_'.$oid.'.pdf' : null);
                        }
                        // 3) Pastabos pakeistos, BET statusas NE pasikeitė šią pačią užklausą
                        //    (kad nesiųstume dviejų laiškų, jei abu pasikeitė vienu metu)
                        elseif ($notesChanged) {
                            $html = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'><div style='background:#1e293b;color:white;padding:20px 30px;border-radius:10px 10px 0 0'><h1 style='margin:0;font-size:20px'>Pastaba apie jūsų užsakymą</h1><p style='margin:5px 0 0;color:#94a3b8'>{$oid}</p></div><div style='background:white;padding:20px 30px;border:1px solid #e2e8f0;border-radius:0 0 10px 10px'><p>Sveiki, <strong>{$custName}</strong>!</p><div style='background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:14px;margin-top:10px'><p style='margin:0;font-size:13.5px;color:#78350f'>".nl2br(htmlspecialchars($updatedOrder['notes']))."</p></div></div></div>";
                            $sendOrderEmail("Pastaba apie užsakymą - {$oid}", $html);
                        }
                    } else {
                        @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] PHPMailer files not found at '.$phpmailerPath."\n", FILE_APPEND);
                    }
                }
            } catch (\Throwable $e) {
                @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] update_order email block failed: '.$e->getMessage()."\n", FILE_APPEND);
            }
        }

        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'delete_order') {
        $data = json_decode(file_get_contents('php://input'), true);
        $orders = array_filter(loadOrders(), fn($o) => $o['id'] !== $data['id']);
        saveOrders($orders);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'save_product') {
        $data = json_decode(file_get_contents('php://input'), true);
        $products = loadProducts();
        if (!empty($data['id'])) {
            foreach ($products as &$p) { if ($p['id'] === $data['id']) { $p = array_merge($p, $data); break; } }
        } else {
            $data['id'] = 'PRD-'.substr(md5(uniqid()),0,6);
            $data['display_code'] = generateUniqueDisplayCode($products);
            $data['created'] = date('Y-m-d H:i:s');
            $products[] = $data;
        }
        saveProducts($products);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'delist_product') {
        $data = json_decode(file_get_contents('php://input'), true);
        $products = loadProducts();
        $found = false;
        foreach ($products as &$p) {
            if (($p['id'] ?? null) === ($data['id'] ?? '_') || (!empty($data['sku']) && ($p['sku'] ?? '') === $data['sku'])) {
                $p['stock'] = 0; $found = true;
            }
        }
        unset($p);
        if ($found) saveProducts($products);
        echo json_encode(['ok'=>$found]); exit;
    }

    if ($action === 'delete_product') {
        $data = json_decode(file_get_contents('php://input'), true);
        $products = array_filter(loadProducts(), fn($p) => $p['id'] !== $data['id']);
        saveProducts($products);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'check_images') {
        $products = loadProducts();
        $results = [];

        foreach ($products as $p) {
            $images = $p['images'] ?? [];
            if (empty($images)) {
                $results[] = [
                    'id' => $p['id'], 'name' => $p['name'] ?? $p['sku'] ?? '?',
                    'total' => 0, 'broken' => 0, 'status' => 'no_images'
                ];
                continue;
            }

            $brokenCount = 0;
            foreach ($images as $img) {
                $isExternal = preg_match('/^https?:\/\//i', $img);
                if ($isExternal) {
                    // Allegro / išorinis URL — tikrinam HTTP atsaką (greitas HEAD)
                    $ch = curl_init($img);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                    curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($httpCode < 200 || $httpCode >= 400) $brokenCount++;
                } else {
                    // Lokalus failas — tikrinam egzistavimą diske
                    $localPath = __DIR__ . '/uploads/products/' . $img;
                    if (!file_exists($localPath)) $brokenCount++;
                }
            }

            $total = count($images);
            $status = $brokenCount === 0 ? 'ok' : ($brokenCount === $total ? 'all_broken' : 'partial_broken');

            $results[] = [
                'id' => $p['id'], 'name' => $p['name'] ?? $p['sku'] ?? '?',
                'total' => $total, 'broken' => $brokenCount, 'status' => $status
            ];
        }

        echo json_encode(['ok'=>true, 'results'=>$results]); exit;
    }

    if ($action === 'update_customer') {
        $data = json_decode(file_get_contents('php://input'), true);
        $users = loadUsers();
        foreach ($users as &$u) {
            if ($u['id'] === $data['id']) {
                if (isset($data['name']) && $data['name'] !== '') $u['name'] = $data['name'];
            }
        }
        unset($u);
        file_put_contents(__DIR__.'/users.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'delete_customer') {
        $data = json_decode(file_get_contents('php://input'), true);
        $users = loadUsers();
        // Paskyra ištrinama, BET jo užsakymų istorija orders.json FAILE NELIEČIAMA —
        // taip išlaikome pardavimų/apskaitos duomenis net pašalinus klientą.
        $users = array_values(array_filter($users, fn($u) => $u['id'] !== $data['id']));
        file_put_contents(__DIR__.'/users.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'answer_question') {
        $data = json_decode(file_get_contents('php://input'), true);
        $questions = loadQuestions();
        foreach ($questions as &$q) {
            if ($q['id'] === $data['id']) {
                $q['answer'] = trim($data['answer'] ?? '');
                $q['status'] = $q['answer'] !== '' ? 'Atsakyta' : 'Nauja';
                $q['answered_at'] = $q['answer'] !== '' ? date('Y-m-d H:i:s') : null;
            }
        }
        unset($q);
        saveQuestions($questions);

        // Nusiunčiam atsakymą klientui el. paštu — klaidos NIEKADA nestabdo atsakymo
        try {
            $cfg = loadSettings();
            if (!empty($cfg['gmail_user']) && !empty($cfg['gmail_pass'])) {
                $target = null;
                foreach ($questions as $q) { if ($q['id'] === $data['id']) { $target = $q; break; } }

                if ($target && !empty($target['email'])) {
                    $phpmailerPath = __DIR__.'/PHPMailer/src/';
                    if (file_exists($phpmailerPath.'Exception.php') && file_exists($phpmailerPath.'PHPMailer.php') && file_exists($phpmailerPath.'SMTP.php')) {
                        require_once $phpmailerPath.'Exception.php';
                        require_once $phpmailerPath.'PHPMailer.php';
                        require_once $phpmailerPath.'SMTP.php';

                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = $cfg['gmail_user'];
                            $mail->Password = $cfg['gmail_pass'];
                            $mail->SMTPSecure = 'tls';
                            $mail->Port = 587;
                            $mail->CharSet = 'UTF-8';
                            $mail->setFrom($cfg['gmail_user'], $cfg['site_name'] ?? 'Parduotuvė');
                            $mail->addReplyTo($cfg['gmail_user'], $cfg['site_name'] ?? 'Parduotuvė');
                            $mail->addAddress($target['email']);
                            $mail->isHTML(true);
                            $mail->Subject = 'Atsakymas į jūsų klausimą apie ' . $target['product_name'];
                            $ansRich = (strip_tags($target['answer']) !== $target['answer']) ? $target['answer'] : nl2br(htmlspecialchars($target['answer']));
                            $mail->Body = "<p>Sveiki, {$target['name']}!</p><p>Jūsų klausimas: <em>" . htmlspecialchars($target['message']) . "</em></p><div>Mūsų atsakymas:<br>" . $ansRich . "</div>";
                            $mail->AltBody = "Sveiki, {$target['name']}!\n\nJūsų klausimas: {$target['message']}\n\nMūsų atsakymas: " . trim(strip_tags($target['answer']));
                            $mail->send();
                        } catch (\Throwable $e) {
                            @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] Question answer email failed: '.$e->getMessage()."\n", FILE_APPEND);
                        }
                    } else {
                        @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] PHPMailer files not found at '.$phpmailerPath."\n", FILE_APPEND);
                    }
                }
            }
        } catch (\Throwable $e) {
            @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s').'] Unexpected answer_question mail error: '.$e->getMessage()."\n", FILE_APPEND);
        }

        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'delete_question') {
        $data = json_decode(file_get_contents('php://input'), true);
        $questions = array_filter(loadQuestions(), fn($q) => $q['id'] !== $data['id']);
        saveQuestions($questions);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'test_gmail_connection') {
        $data = json_decode(file_get_contents('php://input'), true);
        $testUser = trim($data['gmail_user'] ?? '');
        $testPass = trim($data['gmail_pass'] ?? '');

        if (!$testUser || !$testPass) {
            echo json_encode(['ok'=>false, 'msg'=>'Trūksta duomenų']); exit;
        }

        $phpmailerPath = __DIR__.'/PHPMailer/src/';
        if (!file_exists($phpmailerPath.'Exception.php') || !file_exists($phpmailerPath.'PHPMailer.php') || !file_exists($phpmailerPath.'SMTP.php')) {
            echo json_encode(['ok'=>false, 'msg'=>'PHPMailer failai nerasti serveryje. Įkelkite PHPMailer/src/ aplanką.']); exit;
        }

        require_once $phpmailerPath.'Exception.php';
        require_once $phpmailerPath.'PHPMailer.php';
        require_once $phpmailerPath.'SMTP.php';

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $testUser;
            $mail->Password = $testPass;
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($testUser, 'market test');
            $mail->addReplyTo($testUser, 'market test');
            $mail->addAddress($testUser);
            $mail->isHTML(true);
            $mail->Subject = 'Testinis laiškas — Gmail prisijungimas veikia!';
            $mail->Body = '<p>Sveiki! Šis laiškas patvirtina, kad jūsų Gmail App Password admin panelėje yra teisingas ir el. laiškų siuntimas veikia.</p>';
            $mail->AltBody = 'Sveiki! Šis laiškas patvirtina, kad jūsų Gmail App Password admin panelėje yra teisingas ir el. laiškų siuntimas veikia.';
            $mail->send();
            echo json_encode(['ok'=>true]);
        } catch (\Throwable $e) {
            $errMsg = $e->getMessage();
            // Paaiškinam dažniausią klaidos priežastį žmogui suprantama kalba
            $friendlyMsg = $errMsg;
            if (stripos($errMsg, 'authenticate') !== false || stripos($errMsg, 'Username and Password not accepted') !== false) {
                $friendlyMsg = 'Gmail atmetė prisijungimą — patikrinkite, ar tikrai naudojate App Password (16 simbolių kodą), ne įprastą Gmail slaptažodį, ir ar 2 žingsnių patvirtinimas įjungtas jūsų Google paskyroje. Klaida: '.$errMsg;
            }
            echo json_encode(['ok'=>false, 'msg'=>$friendlyMsg]);
        }
        exit;
    }

    if ($action === 'save_settings') {
        $data = json_decode(file_get_contents('php://input'), true);
        saveSettings($data);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'upload_image') {
        if (!empty($_FILES['image'])) {
            $dir = 'uploads/products/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) { echo json_encode(['ok'=>false,'msg'=>'Netinkamas formatas']); exit; }
            $fname = 'p_'.uniqid().'.'.$ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $dir.$fname);
            echo json_encode(['ok'=>true,'file'=>$fname]); exit;
        }
        echo json_encode(['ok'=>false]); exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;
}

// ── LOAD DATA ─────────────────────────────────────────────
$orders   = loadOrders();
$users    = loadUsers();
$products = loadProducts();
$settings = loadSettings();
$questions = loadQuestions();

// Migracija: senesni produktai gali neturėti display_code — sugeneruojam ir išsaugom
$needsMigration = false;
foreach ($products as &$p) {
    if (empty($p['display_code'])) {
        $p['display_code'] = generateUniqueDisplayCode($products);
        $needsMigration = true;
    }
}
unset($p);
if ($needsMigration) {
    saveProducts($products);
}

$ordersRev = array_reverse($orders);
$statuses  = ['Submitted','Confirmed','Processed','Completed','Cancelled'];

// Stats
$totalRevenue = array_sum(array_map(fn($o) => floatval($o['total']), array_filter($orders, fn($o) => $o['status'] !== 'Cancelled')));
$todayOrders  = count(array_filter($orders, fn($o) => substr($o['date'],0,10) === date('Y-m-d')));
$newOrders    = count(array_filter($orders, fn($o) => $o['status'] === 'Submitted'));
$totalStock   = array_sum(array_column($products, 'stock'));

// Revenue by day (last 7)
$revenueByDay = [];
for ($i=6;$i>=0;$i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $revenueByDay[$d] = array_sum(array_map(fn($o)=>floatval($o['total']), array_filter($orders, fn($o)=>substr($o['date'],0,10)===$d && $o['status']!=='Cancelled')));
}
// Statusų informacija (pagal dizainą)
$statusInfo = [
  'Submitted' => ['lt'=>'Pateiktas','c'=>'#2A6FDB','bg'=>'#EFF4FE'],
  'Confirmed' => ['lt'=>'Patvirtintas','c'=>'#B58100','bg'=>'#FFF8E6'],
  'Processed' => ['lt'=>'Apdorotas','c'=>'#7C3AED','bg'=>'#F3EEFE'],
  'Completed' => ['lt'=>'Įvykdytas','c'=>'#1F8A5B','bg'=>'#E9F8F0'],
  'Cancelled' => ['lt'=>'Atšauktas','c'=>'#E11D48','bg'=>'#FEF0F3'],
];
$siFn = fn($s)=>$statusInfo[$s] ?? ['lt'=>$s,'c'=>'#5C6B7E','bg'=>'#F2F4F7'];
// Trendai (paskutinės 7 d. vs ankstesnės 7 d.)
$rev_last7 = array_sum($revenueByDay);
$rev_prev7 = 0;
for ($i=13;$i>=7;$i--){ $d=date('Y-m-d',strtotime("-{$i} days")); $rev_prev7 += array_sum(array_map(fn($o)=>floatval($o['total']),array_filter($orders,fn($o)=>substr($o['date'],0,10)===$d && $o['status']!=='Cancelled'))); }
$revTrend = $rev_prev7>0 ? round(($rev_last7-$rev_prev7)/$rev_prev7*100) : ($rev_last7>0?100:0);
$lowStockCount = count(array_filter($products, fn($p)=>(int)($p['stock']??0)<=3));
$newUsers7 = count(array_filter($users, function($u){ $j=$u['created_at']??$u['joined']??$u['registered']??''; return $j && strtotime($j)>=strtotime('-7 days'); }));
// Santykinis laikas (prieš X val./d.)
$relTime = function($ts){
    if(!$ts) return '';
    $t = is_numeric($ts) ? (int)$ts : strtotime($ts);
    if(!$t) return htmlspecialchars($ts);
    $diff = time()-$t;
    if($diff < 3600) return 'prieš '.max(1,floor($diff/60)).' min.';
    if($diff < 86400) return 'prieš '.floor($diff/3600).' val.';
    if($diff < 172800) return 'vakar';
    if($diff < 604800) return 'prieš '.floor($diff/86400).' d.';
    return date('Y-m-d', $t);
};

SHOW_LOGIN:
?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin | <?=htmlspecialchars($loginSiteName)?></title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif;background:#F7F8FA;color:#0F1B2D}
:root{--sidebar:248px;--accent:#FF5A33;--accent-dark:#E8431B;--danger:#DC2626;--success:#1F8A5B;--warning:#D97706;--ink:#0B1929;--text:#0F1B2D;--text2:#5C6B7E;--text3:#94A4B5;--border:#EAEDF2;--bg:#F7F8FA}

/* LOGIN */
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(160deg,#0B1929 0%,#13233A 55%,#3A1F18 100%)}
.login-card{background:white;border-radius:22px;padding:40px;width:100%;max-width:392px;box-shadow:0 40px 80px -24px rgba(0,0,0,.5)}
.login-logo{text-align:center;margin-bottom:28px}
.login-logo i{font-size:36px;color:var(--accent)}
.login-logo h1{font-family:'Sora',sans-serif;font-size:21px;font-weight:800;margin-top:8px;letter-spacing:-.5px}
.login-logo p{color:var(--text2);font-size:13px;margin-top:4px}
.form-input{width:100%;padding:13px 14px;border:1.5px solid #E4E8EE;border-radius:13px;font-family:inherit;font-size:14px;outline:none;transition:border-color .2s,box-shadow .2s}
.form-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px #FFEDE6}
.btn-primary{width:100%;padding:13px;background:var(--accent);color:white;border:none;border-radius:13px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;transition:transform .15s,background .2s;margin-top:12px;box-shadow:0 14px 30px -12px rgba(255,90,51,.55)}
.btn-primary:hover{background:var(--accent-dark);transform:translateY(-1px)}
.login-err{background:#FEF2F2;color:#DC2626;border-radius:11px;padding:10px 14px;font-size:13px;margin-bottom:12px}

/* LAYOUT */
.layout{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar);background:var(--ink);position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:50;display:flex;flex-direction:column}
.sidebar-logo{padding:22px 22px 18px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;gap:11px}
.sidebar-logo i{font-size:22px;color:var(--accent)}
.sidebar-logo span{font-family:'Sora',sans-serif;font-size:17px;font-weight:800;color:white;line-height:1.2;letter-spacing:-.5px}
.sidebar-logo small{color:#647689;font-size:10.5px;display:block;font-weight:600;letter-spacing:.5px}
.sidebar-nav{flex:1;padding:14px 12px}
.nav-section{padding:14px 10px 6px;font-size:10.5px;font-weight:700;letter-spacing:1px;color:#647689;text-transform:uppercase}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;color:#94A4B5;cursor:pointer;transition:all .15s;border-radius:11px;font-size:13.5px;font-weight:600;text-decoration:none;margin-bottom:2px}
.nav-item:hover{background:rgba(255,255,255,.06);color:white}
.nav-item.active{background:var(--accent);color:white;box-shadow:0 10px 22px -10px rgba(255,90,51,.6)}
.nav-item i{width:16px;text-align:center;font-size:14px}
.nav-badge{margin-left:auto;background:var(--danger);color:white;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
.nav-item.active .nav-badge{background:rgba(255,255,255,.25)}
.sidebar-footer{padding:16px;border-top:1px solid rgba(255,255,255,.07)}
.sidebar-footer a{display:flex;align-items:center;gap:8px;color:#647689;font-size:12px;text-decoration:none;transition:color .2s;font-weight:600}
.sidebar-footer a:hover{color:#94A4B5}

.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:rgba(255,255,255,.82);-webkit-backdrop-filter:blur(20px) saturate(1.4);backdrop-filter:blur(20px) saturate(1.4);border-bottom:1px solid var(--border);padding:0 26px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40}
.topbar-title{font-family:'Sora',sans-serif;font-size:19px;font-weight:800;letter-spacing:-.5px}
.topbar-right{display:flex;align-items:center;gap:12px}
.topbar-btn{padding:8px 15px;border-radius:11px;font-size:13px;font-weight:700;cursor:pointer;border:none;transition:all .2s;font-family:inherit}
.topbar-btn.outline{background:#fff;border:1px solid var(--border);color:var(--text2)}
.topbar-btn.outline:hover{background:#FFEDE6;border-color:var(--accent);color:var(--accent)}
.content{padding:26px;flex:1}

/* PANELS */
.panel{display:none}.panel.active{display:block}

/* STATS GRID */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
@media(max-width:900px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
.stat-card{background:white;border-radius:20px;padding:22px;border:1px solid var(--border)}
.stat-label{font-size:11.5px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.stat-value{font-family:'Sora',sans-serif;font-size:28px;font-weight:800;letter-spacing:-1px;margin-bottom:4px}
.stat-sub{font-size:12px;color:var(--text2)}
.stat-icon{width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px}
.si-blue{background:#E8F0FE;color:#2A6FDB}
.si-green{background:#E7F6EF;color:#1F8A5B}
.si-orange{background:#FFEDE6;color:#FF5A33}
.si-purple{background:#F3E8FF;color:#9333EA}

/* CHART */
.chart-card{background:white;border-radius:20px;padding:24px;border:1px solid var(--border);margin-bottom:24px}
.chart-title{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;margin-bottom:16px;color:var(--text)}
.chart-wrap{height:200px;position:relative}

/* QUICK STATS ROW */
.quick-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
.quick-card{background:white;border-radius:20px;padding:24px;border:1px solid var(--border)}
.quick-title{font-family:'Sora',sans-serif;font-size:15px;font-weight:700;color:var(--text);margin-bottom:14px;display:flex;align-items:center;gap:6px}

/* TABLE */
.table-card{background:white;border-radius:20px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px}
.table-header{padding:18px 24px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #F2F4F7;flex-wrap:wrap;gap:10px}
.table-title{font-family:'Sora',sans-serif;font-size:16px;font-weight:700}
.table-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.search-box{padding:8px 13px;border:1px solid var(--border);border-radius:11px;font-size:13px;outline:none;width:210px;font-family:inherit;transition:border-color .15s,box-shadow .15s}
.search-box:focus{border-color:var(--accent);box-shadow:0 0 0 3px #FFEDE6}
.filter-select{padding:8px 12px;border:1px solid var(--border);border-radius:11px;font-size:13px;outline:none;background:white;cursor:pointer;font-family:inherit}
table{width:100%;border-collapse:collapse}
th{padding:11px 18px;text-align:left;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;background:#FAFBFC;border-bottom:1px solid var(--border)}
td{padding:13px 18px;font-size:13px;border-bottom:1px solid #F2F4F7;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#FAFBFC}
.badge{padding:3px 11px;border-radius:20px;font-size:11px;font-weight:700;display:inline-block}
.badge-blue{background:#E8F0FE;color:#2A6FDB}
.badge-yellow{background:#FFF8EC;color:#B45309}
.badge-purple{background:#F3E8FF;color:#7C3AED}
.badge-green{background:#E7F6EF;color:#1F8A5B}
.badge-red{background:#FEF2F2;color:#DC2626}
.badge-gray{background:#F2F4F7;color:#5C6B7E}

/* BUTTONS */
.btn{padding:8px 14px;border-radius:11px;font-size:12px;font-weight:700;cursor:pointer;border:none;transition:all .2s;display:inline-flex;align-items:center;gap:5px;font-family:inherit}
.btn-sm{padding:6px 11px;font-size:11px}
.btn-blue{background:#E8F0FE;color:#2A6FDB}.btn-blue:hover{background:#d6e4fc}
.btn-green{background:#E7F6EF;color:#1F8A5B}.btn-green:hover{background:#d3efe1}
.btn-red{background:#FEF2F2;color:#DC2626}.btn-red:hover{background:#fde0e0}
.btn-gray{background:#F2F4F7;color:#5C6B7E}.btn-gray:hover{background:#e7eaef}
.btn-solid-blue{background:var(--accent);color:white}.btn-solid-blue:hover{background:var(--accent-dark)}
.btn-solid-green{background:var(--success);color:white}.btn-solid-green:hover{background:#176f49}
.btn-solid-red{background:var(--danger);color:white}.btn-solid-red:hover{background:#b91c1c}

/* MODAL */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(11,25,41,.5);z-index:200;overflow-y:auto;padding:20px;-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px)}
.modal-bg.open{display:flex;align-items:flex-start;justify-content:center}
.modal{background:white;border-radius:22px;width:100%;max-width:600px;overflow:hidden;box-shadow:0 40px 80px -24px rgba(11,25,41,.4)}
.modal-head{padding:22px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
.modal-head h3{font-family:'Sora',sans-serif;font-size:17px;font-weight:700}
.modal-close{background:none;border:none;font-size:20px;color:var(--text3);cursor:pointer}
.modal-close:hover{color:var(--text)}
.modal-body{padding:24px}
.modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:12px;font-weight:700;color:var(--text2);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.form-ctrl{width:100%;padding:11px 13px;border:1.5px solid #E4E8EE;border-radius:11px;font-family:inherit;font-size:13px;outline:none;transition:border-color .2s,box-shadow .2s}
.form-ctrl:focus{border-color:var(--accent);box-shadow:0 0 0 3px #FFEDE6}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:560px){.form-grid{grid-template-columns:1fr}}
.form-ctrl.full{grid-column:1/-1}
select.form-ctrl{background:white;cursor:pointer}
textarea.form-ctrl{resize:vertical;min-height:80px}

/* PRODUCT */
.prod-img{width:48px;height:48px;object-fit:cover;border-radius:11px;border:1px solid var(--border)}
.prod-img-ph{width:48px;height:48px;background:#F2F4F7;border-radius:11px;display:flex;align-items:center;justify-content:center;color:var(--text3);font-size:18px;border:1px solid var(--border)}
.stock-badge{padding:2px 8px;border-radius:7px;font-size:11px;font-weight:700}
.stock-ok{background:#E7F6EF;color:#1F8A5B}
.stock-low{background:#FFF8EC;color:#B45309}
.stock-out{background:#FEF2F2;color:#DC2626}

/* UPLOAD */
.upload-zone{border:2px dashed #D6DBE3;border-radius:16px;padding:28px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s}
.upload-zone:hover{border-color:var(--accent);background:#FFF8F6}
.img-preview-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.img-preview{position:relative;width:72px;height:72px}
.img-preview img{width:100%;height:100%;object-fit:cover;border-radius:11px;border:1px solid var(--border)}
.img-remove{position:absolute;top:-6px;right:-6px;background:var(--danger);color:white;border:none;border-radius:50%;width:18px;height:18px;font-size:10px;cursor:pointer;display:flex;align-items:center;justify-content:center}

/* SETTINGS */
.settings-section{background:white;border-radius:20px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px}
.settings-head{padding:18px 24px;border-bottom:1px solid #F2F4F7;font-family:'Sora',sans-serif;font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px}
.settings-body{padding:24px}

/* ORDER DETAIL */
.order-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
@media(max-width:560px){.order-detail-grid{grid-template-columns:1fr}}
.detail-box{background:#FAFBFC;border:1px solid var(--border);border-radius:14px;padding:16px}
.detail-label{font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.detail-val{font-size:13px;color:var(--text);line-height:1.8}
.timeline{padding:12px 0}
.timeline-item{display:flex;gap:12px;padding:6px 0}
.tl-dot{width:8px;height:8px;border-radius:50%;background:var(--text3);margin-top:5px;flex-shrink:0}
.tl-dot.done{background:var(--accent)}
.tl-text{font-size:12px;color:var(--text2)}
.tl-time{font-size:11px;color:var(--text3)}

/* TOAST */
.toast{position:fixed;bottom:24px;right:24px;background:var(--ink);color:white;padding:13px 20px;border-radius:13px;font-size:13px;font-weight:600;z-index:999;opacity:0;transform:translateY(10px);transition:all .3s;pointer-events:none;box-shadow:0 18px 40px -16px rgba(11,25,41,.5)}
.toast.show{opacity:1;transform:translateY(0)}

/* ── DASHBOARD (pagal dizainą) ── */
.dash-stack{display:flex;flex-direction:column;gap:20px;animation:rise .4s ease both}
@keyframes rise{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.dash-2col{display:grid;gap:16px}
@media(max-width:960px){.dash-2col{grid-template-columns:1fr!important}}
.dash-card{background:#fff;border:1px solid var(--border);border-radius:20px;padding:22px 24px}
.dash-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px}
.dash-card-title{font-family:'Sora',sans-serif;font-size:16px;font-weight:700;color:var(--text)}
.dash-card-sub{font-size:12px;color:var(--text3);margin-top:2px}
.stat-card{transition:transform .2s,box-shadow .2s}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 16px 34px -18px rgba(11,25,41,.25)}
.stat-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.stat-icon{margin-bottom:0}
.trend-badge{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;padding:4px 9px;border-radius:8px}
.trend-badge i{font-size:9px}
.trend-up{color:#1F8A5B;background:#E9F8F0}
.trend-down{color:#E11D48;background:#FEF0F3}
/* segmentinis perjungiklis */
.seg{display:flex;gap:6px}
.seg-item{font-size:12px;font-weight:700;color:var(--text2);background:#F2F4F7;padding:7px 12px;border-radius:9px;cursor:pointer}
.seg-item.active{color:#fff;background:var(--ink)}
/* CSS stulpelinė diagrama */
.bars{display:flex;align-items:flex-end;justify-content:space-between;gap:14px}
.bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:9px}
.bar-val{font-size:11px;font-weight:700;color:var(--text)}
.bar-track{width:100%;max-width:42px;height:128px;display:flex;align-items:flex-end}
.bar-fill{width:100%;border-radius:10px 10px 4px 4px;background:linear-gradient(180deg,#D9E0EA,#C5CFDB);animation:growbar .7s cubic-bezier(.2,.8,.2,1)}
.bar-fill.last{background:linear-gradient(180deg,#FF7A52,#FF5A33)}
.bar-day{font-size:11px;color:var(--text3);font-weight:600;text-transform:capitalize}
@keyframes growbar{from{height:0}}
/* statusų eilutės */
.status-row{margin-bottom:15px}
.status-row-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:7px}
.status-name{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--text)}
.status-dot{width:9px;height:9px;border-radius:50%}
.status-count{font-size:13px;font-weight:700;font-family:'Sora',sans-serif}
.status-track{height:7px;background:#F2F4F7;border-radius:5px;overflow:hidden}
.status-fill{height:100%;border-radius:5px;transition:width .6s ease}
/* naujausi užsakymai / mažas likutis eilutės */
.sec-all{font-size:12.5px;font-weight:700;color:var(--accent);cursor:pointer}
.recent-row{display:flex;align-items:center;gap:13px;padding:12px 22px;border-top:1px solid #F2F4F7;cursor:pointer;transition:background .15s}
.recent-row:hover{background:#FAFBFC}
.recent-ava{width:38px;height:38px;border-radius:11px;background:#F2F4F7;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-weight:700;font-size:13px;color:var(--text2);flex-shrink:0}
.recent-name{font-size:13.5px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.recent-meta{font-size:11.5px;color:var(--text3)}
.recent-total{font-family:'Sora',sans-serif;font-size:14px;font-weight:700}
.badge-dot{display:inline-block;font-size:10.5px;font-weight:700;padding:2px 9px;border-radius:20px;margin-top:3px}
/* šoninės juostos vartotojo kortelė */
.sidebar-user{display:flex;align-items:center;gap:11px;padding:11px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:14px}
.sidebar-user .ava{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#FF7A52,#FF5A33);display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-weight:800;font-size:13px;color:#fff;flex-shrink:0}
.sidebar-user .nm{font-size:13px;font-weight:700;color:#fff}
.sidebar-user .rl{font-size:11px;color:#647689}
.sidebar-user a{color:#647689;font-size:15px;transition:color .15s}
.sidebar-user a:hover{color:#fff}

/* ── UŽSAKYMAI (pagal dizainą) ── */
.ord-head{display:flex;align-items:center;gap:14px;padding:18px 24px;flex-wrap:wrap}
.ord-search{display:flex;align-items:center;gap:8px;background:#F7F8FA;border:1px solid var(--border);border-radius:11px;padding:8px 13px;min-width:220px}
.ord-search i{font-size:12px;color:var(--text3)}
.ord-search input{border:none;outline:none;background:transparent;font-size:13px;font-family:inherit;width:100%;color:var(--text)}
.btn-xlsx{display:inline-flex;align-items:center;gap:7px;background:var(--success);color:#fff;border:none;border-radius:11px;padding:9px 15px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s}
.btn-xlsx:hover{background:#176f49}
.ord-filters{display:flex;gap:7px;padding:14px 24px;border-bottom:1px solid var(--border);flex-wrap:wrap}
.ofilter{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;padding:7px 13px;border-radius:10px;cursor:pointer;color:var(--text2);background:#F2F4F7;transition:all .15s}
.ofilter:hover{background:#e7eaef}
.ofilter.active{color:#fff;background:var(--ink)}
.act-btn{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:#fff;cursor:pointer;font-size:13px;display:inline-flex;align-items:center;justify-content:center;transition:background .2s}
.act-view{color:#2A6FDB}.act-view:hover{background:#EFF4FE}
.act-del{color:#E11D48}.act-del:hover{background:#FEF2F4}

/* ── KLAUSIMAI (pagal dizainą) ── */
.q-list{display:flex;flex-direction:column;gap:13px;animation:rise .4s ease both}
.q-card{background:#fff;border:1px solid var(--border);border-radius:18px;padding:20px 22px}
.q-top{display:flex;align-items:center;gap:11px;margin-bottom:12px}
.q-ava{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-family:'Sora',sans-serif;font-weight:700;font-size:13px;color:#fff;flex-shrink:0}
.q-name{font-size:13.5px;font-weight:700;color:var(--text)}
.q-meta{font-size:11.5px;color:var(--text3)}
.q-badge{font-size:11px;font-weight:700;padding:4px 11px;border-radius:20px}
.q-badge.new{color:#2A6FDB;background:#EFF4FE}
.q-badge.answered{color:#1F8A5B;background:#E9F8F0}
.q-text{font-size:14px;color:var(--text);line-height:1.55;background:#FAFBFC;border:1px solid var(--border);border-radius:13px;padding:13px 15px;margin-bottom:13px}
.q-answer-old{font-size:13px;color:var(--text2);line-height:1.55;background:#E9F8F0;border:1px solid #cdeede;border-radius:13px;padding:11px 15px;margin-bottom:13px}
.q-reply{display:flex;gap:9px}
.q-reply input{flex:1;border:1px solid var(--border);border-radius:11px;padding:11px 14px;font-size:13.5px;font-family:inherit;outline:none;transition:border-color .15s,box-shadow .15s}
.q-reply input:focus{border-color:var(--accent);box-shadow:0 0 0 3px #FFEDE6}
.q-reply button{background:var(--accent);color:#fff;border:none;border-radius:11px;padding:11px 22px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s,transform .15s}
.q-reply button:hover{background:var(--accent-dark);transform:translateY(-1px)}

/* Klausimų filtrai + kontaktai + redaktorius */
.q-filters{display:flex;gap:7px;margin-bottom:16px;flex-wrap:wrap}
.qnew-count{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;background:var(--danger);color:#fff;border-radius:9px;font-size:11px;font-weight:800}
.ofilter.active .qnew-count{background:#fff;color:var(--danger)}
.q-contact{display:flex;flex-wrap:wrap;gap:8px 16px;margin-bottom:12px;font-size:12px;color:var(--text2)}
.q-contact span{display:inline-flex;align-items:center;gap:6px}
.q-contact i{color:var(--text3);font-size:11px}
.q-contact a{color:var(--accent);text-decoration:none}
.q-contact a:hover{text-decoration:underline}
.q-contact code{background:#F2F4F7;padding:1px 6px;border-radius:5px;font-size:11.5px}
.q-reply-wrap{margin-top:4px}
.q-toolbar{display:flex;gap:4px;margin-bottom:8px;flex-wrap:wrap}
.q-toolbar button{width:32px;height:32px;border:1px solid var(--border);background:#fff;border-radius:9px;cursor:pointer;color:var(--text2);font-size:12px;transition:all .15s}
.q-toolbar button:hover{background:var(--accentSoft);color:var(--accent);border-color:var(--accent)}
.q-editor{min-height:130px;max-height:420px;overflow-y:auto;border:1px solid var(--border);border-radius:13px;padding:13px 15px;font-size:14px;line-height:1.6;color:var(--text);outline:none;transition:border-color .15s,box-shadow .15s,min-height .2s;background:#fff}
.q-editor:focus{border-color:var(--accent);box-shadow:0 0 0 3px #FFEDE6;min-height:190px}
.q-editor:empty:before{content:attr(data-ph);color:var(--text3)}
.q-editor img{max-width:100%;border-radius:8px;margin:6px 0}
.q-send-btn{display:inline-flex;align-items:center;gap:8px;background:var(--accent);color:#fff;border:none;border-radius:11px;padding:11px 22px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s,transform .15s;box-shadow:0 12px 26px -12px rgba(255,90,51,.5)}
.q-send-btn:hover{background:var(--accent-dark);transform:translateY(-1px)}

/* DASHBOARD (Konsolė) — pagal MarketAdmin dizainą */
.seg{display:inline-flex;background:#F2F4F7;border-radius:11px;padding:3px;gap:2px}
.seg span{padding:5px 13px;border-radius:8px;font-size:12px;font-weight:700;color:var(--text2);cursor:pointer;transition:all .15s}
.seg span.active{background:#fff;color:var(--text);box-shadow:0 1px 3px rgba(11,25,41,.12)}
.dash-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:16px;margin-bottom:16px}
.dash-grid2{display:grid;grid-template-columns:1.5fr 1fr;gap:16px;margin-bottom:16px}
@media(max-width:980px){.dash-grid,.dash-grid2{grid-template-columns:1fr}}
.card{background:#fff;border:1px solid var(--border);border-radius:20px;padding:22px 24px}
.card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;gap:12px;flex-wrap:wrap}
.card-h{font-family:'Sora',sans-serif;font-size:16px;font-weight:700}
.card-sub{font-size:11.5px;color:var(--text3);margin-top:3px}
.card-link{font-size:13px;font-weight:700;color:var(--accent);cursor:pointer;text-decoration:none}
.avatar{width:38px;height:38px;border-radius:11px;background:var(--accentSoft);color:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;font-family:'Sora',sans-serif;flex-shrink:0}
.list-row{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid #F2F4F7}
.list-row:last-child{border-bottom:none}
.status-row{display:flex;align-items:center;gap:12px;padding:9px 0}
.status-bar{flex:1;height:7px;background:#F2F4F7;border-radius:4px;overflow:hidden}
.status-bar>div{height:100%;background:var(--accent);border-radius:4px}
.sidebar-user{display:flex;align-items:center;gap:11px;padding:11px;margin:6px 12px 10px;background:rgba(255,255,255,.05);border-radius:13px}
.sidebar-user .av{width:38px;height:38px;border-radius:11px;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-family:'Sora',sans-serif;flex-shrink:0}
.sidebar-user .nm{font-size:13px;font-weight:700;color:#fff}
.sidebar-user .rl{font-size:11px;color:#647689}
.dash-bars{display:flex;align-items:flex-end;gap:10px;height:170px;padding-top:10px}
.dash-bars .bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:7px;height:100%;justify-content:flex-end}
.dash-bars .bar{width:100%;max-width:34px;background:linear-gradient(180deg,#FF7A57,#FF5A33);border-radius:8px 8px 4px 4px;min-height:4px;transition:height .4s}
.dash-bars .bar-day{font-size:10.5px;color:var(--text3);font-weight:600}
.dash-bars .bar-val{font-size:10px;color:var(--text2);font-weight:700}

/* RESPONSIVE */
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);transition:transform .3s}
    .sidebar.open{transform:translateX(0)}
    .main{margin-left:0}
    .stats-grid{grid-template-columns:1fr 1fr}
    .quick-row{grid-template-columns:1fr}
}
</style>
</head>
<body>

<?php if (!isset($_SESSION['admin'])): ?>
<!-- ═══ LOGIN ═══ -->
<div class="login-wrap">
    <div class="login-card">
        <div class="login-logo">
            <i class="fa-solid fa-bolt"></i>
            <h1>market<span style="color:#FF5A33">.</span></h1>
            <p>ADMIN CONSOLE</p>
        </div>
        <?php if (isset($loginError)): ?><div class="login-err"><?= $loginError ?></div><?php endif; ?>
        <form method="POST">
            <input type="password" name="password" class="form-input" placeholder="Slaptažodis" autofocus>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-right-to-bracket mr-2"></i>Prisijungti</button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ═══ APP ═══ -->
<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <i class="fa-solid fa-bolt"></i>
        <div><span>market<span style="color:#FF5A33">.</span></span><small>ADMIN CONSOLE</small></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Pagrindinis</div>
        <a class="nav-item active" onclick="showPanel('dashboard',this)"><i class="fa-solid fa-gauge-high"></i> Konsolė</a>

        <div class="nav-section">Parduotuvė</div>
        <a class="nav-item" onclick="showPanel('orders',this)">
            <i class="fa-solid fa-box"></i> Užsakymai
            <?php if($newOrders>0):?><span class="nav-badge"><?=$newOrders?></span><?php endif;?>
        </a>
        <a class="nav-item" onclick="showPanel('products',this)"><i class="fa-solid fa-tags"></i> Prekės</a>
        <a class="nav-item" onclick="showPanel('import',this)"><i class="fa-solid fa-file-import"></i> Importas</a>
        <a class="nav-item" onclick="showPanel('customers',this)"><i class="fa-solid fa-users"></i> Klientai</a>
        <a class="nav-item" onclick="showPanel('questions',this)">
            <i class="fa-solid fa-comments"></i> Klausimai
            <?php $newQCount = count(array_filter($questions, fn($q)=>($q['status']??'Nauja')==='Nauja')); if($newQCount>0):?><span class="nav-badge"><?=$newQCount?></span><?php endif;?>
        </a>

        <div class="nav-section">Sistema</div>
        <a class="nav-item" onclick="showPanel('settings',this)"><i class="fa-solid fa-gear"></i> Nustatymai</a>
    </nav>
    <div class="sidebar-footer">
        <a href="index.html" target="_blank" style="margin-bottom:12px"><i class="fa-solid fa-store"></i> Atidaryti parduotuvę</a>
        <div class="sidebar-user">
            <div class="ava">AD</div>
            <div style="flex:1;min-width:0">
                <div class="nm">Administratorius</div>
                <div class="rl">Savininkas</div>
            </div>
            <a href="?logout" title="Atsijungti"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
<div class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button class="topbar-btn outline" id="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')" style="display:none"><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title" id="topbar-title">Konsolė</div>
    </div>
    <div class="topbar-right">
        <span style="font-size:12px;color:var(--text2);font-weight:600"><?= date('Y-m-d') ?></span>
        <a href="?logout" class="topbar-btn outline"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</div>

<div class="content">

<!-- ══ DASHBOARD ══ -->
<div class="panel active" id="panel-dashboard">
  <div class="dash-stack">
    <!-- stat cards -->
    <div class="stats-grid">
      <?php
        $maxRev = max(1, max($revenueByDay));
        $cards = [
          ['icon'=>'fa-euro-sign','si'=>'si-orange','label'=>'Pajamos (7 d.)','value'=>number_format($rev_last7,0,',',' ').' €','sub'=>number_format($totalRevenue,0,',',' ').' € iš viso','trend'=>$revTrend,'up'=>$revTrend>=0],
          ['icon'=>'fa-box','si'=>'si-blue','label'=>'Nauji užsakymai','value'=>$newOrders,'sub'=>'Laukia apdorojimo','trend'=>$todayOrders,'up'=>true,'trendTxt'=>$todayOrders.' šiandien'],
          ['icon'=>'fa-users','si'=>'si-green','label'=>'Klientai','value'=>count($users),'sub'=>'Registruotų paskyrų','trend'=>$newUsers7,'up'=>true,'trendTxt'=>'+'.$newUsers7],
          ['icon'=>'fa-warehouse','si'=>'si-purple','label'=>'Prekių likutis','value'=>$totalStock,'sub'=>count($products).' skirtingų prekių','trend'=>$lowStockCount,'up'=>$lowStockCount===0,'trendTxt'=>$lowStockCount.' mažas'],
        ];
        foreach($cards as $c):
          $tcls = $c['up'] ? 'trend-up' : 'trend-down';
          $ticon = $c['up'] ? 'fa-arrow-up' : 'fa-arrow-down';
          $ttxt = $c['trendTxt'] ?? (($c['trend']>=0?'+':'').$c['trend'].'%');
      ?>
      <div class="stat-card">
        <div class="stat-top">
          <span class="stat-icon <?=$c['si']?>"><i class="fa-solid <?=$c['icon']?>"></i></span>
          <span class="trend-badge <?=$tcls?>"><i class="fa-solid <?=$ticon?>"></i> <?=$ttxt?></span>
        </div>
        <div class="stat-label"><?=$c['label']?></div>
        <div class="stat-value"><?=$c['value']?></div>
        <div class="stat-sub"><?=$c['sub']?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- chart + status -->
    <div class="dash-2col" style="grid-template-columns:1.6fr 1fr">
      <div class="dash-card">
        <div class="dash-card-head">
          <div>
            <div class="dash-card-title">Pajamų apžvalga</div>
            <div class="dash-card-sub">Paskutinės 7 dienos</div>
          </div>
          <div class="seg">
            <span class="seg-item active">7d</span>
            <span class="seg-item">30d</span>
            <span class="seg-item">12m</span>
          </div>
        </div>
        <div class="bars">
          <?php $i=0; $n=count($revenueByDay); foreach($revenueByDay as $d=>$v): $h=round($v/$maxRev*100); $last=($i===$n-1); ?>
          <div class="bar-col">
            <div class="bar-val"><?= $v>=1000 ? number_format($v/1000,1,',','').'k' : number_format($v,0,',','') ?></div>
            <div class="bar-track"><div class="bar-fill <?=$last?'last':''?>" style="height:<?=max(3,$h)?>%;animation-delay:<?=$i*0.06?>s"></div></div>
            <div class="bar-day"><?= date('D',strtotime($d)) ?></div>
          </div>
          <?php $i++; endforeach; ?>
        </div>
      </div>

      <div class="dash-card">
        <div class="dash-card-title" style="margin-bottom:16px">Užsakymai pagal statusą</div>
        <?php foreach($statuses as $s): $info=$siFn($s); $cnt=count(array_filter($orders,fn($o)=>$o['status']===$s)); $pct=count($orders)?round($cnt/count($orders)*100):0; ?>
        <div class="status-row">
          <div class="status-row-top">
            <span class="status-name"><span class="status-dot" style="background:<?=$info['c']?>"></span><?=$info['lt']?></span>
            <span class="status-count"><?=$cnt?></span>
          </div>
          <div class="status-track"><div class="status-fill" style="width:<?=$pct?>%;background:<?=$info['c']?>"></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- recent orders + low stock -->
    <div class="dash-2col" style="grid-template-columns:1.5fr 1fr">
      <div class="dash-card" style="padding:0">
        <div class="dash-card-head" style="padding:18px 22px 14px">
          <div class="dash-card-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--accent);margin-right:8px;font-size:14px"></i>Naujausi užsakymai</div>
          <span class="sec-all" onclick="showPanel('orders',document.querySelectorAll('.nav-item')[1])">Visi →</span>
        </div>
        <?php foreach(array_slice($ordersRev,0,5) as $o): $info=$siFn($o['status']);
          $nm=trim(($o['customer']['name']??'').' '.($o['customer']['surname']??''));
          $ini=strtoupper(mb_substr($o['customer']['name']??'?',0,1).mb_substr($o['customer']['surname']??'',0,1));
          $items=is_array($o['cart']??null)?count($o['cart']):($o['items']??0); ?>
        <div class="recent-row" onclick='openOrderModal(<?=htmlspecialchars(json_encode($o),ENT_QUOTES)?>)'>
          <div class="recent-ava"><?=htmlspecialchars($ini)?></div>
          <div style="flex:1;min-width:0">
            <div class="recent-name"><?=htmlspecialchars($nm?:'Klientas')?></div>
            <div class="recent-meta"><?=$o['id']?> · <?=$items?> prekės</div>
          </div>
          <div style="text-align:right">
            <div class="recent-total"><?=htmlspecialchars($o['total'])?></div>
            <span class="badge-dot" style="color:<?=$info['c']?>;background:<?=$info['bg']?>"><?=$info['lt']?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="dash-card" style="padding:0">
        <div class="dash-card-head" style="padding:18px 22px 14px">
          <div class="dash-card-title"><i class="fa-solid fa-triangle-exclamation" style="color:#F5A623;margin-right:8px;font-size:14px"></i>Mažas likutis</div>
        </div>
        <?php $lowStock = array_filter($products, fn($p)=>(int)($p['stock']??0)<=3);
        if(empty($lowStock)): ?>
          <p style="font-size:12.5px;color:var(--text3);padding:8px 22px 20px">Visi produktai turi pakankamą likutį ✓</p>
        <?php else: foreach(array_slice($lowStock,0,6) as $p): $s=(int)($p['stock']??0); ?>
        <div class="recent-row" style="cursor:default">
          <div style="flex:1;min-width:0"><div class="recent-name" style="font-weight:600"><?=htmlspecialchars($p['name'])?></div></div>
          <span class="stock-badge <?=$s===0?'stock-out':($s<=2?'stock-low':'stock-ok')?>"><?=$s===0?'Baigėsi':'Liko '.$s?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ══ ORDERS ══ -->
<div class="panel" id="panel-orders">
    <div class="table-card">
        <div class="ord-head">
            <div class="table-title">Visi užsakymai <span style="color:var(--text3);font-weight:600">(<?=count($orders)?>)</span></div>
            <div style="margin-left:auto;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <div class="ord-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="orders-search" placeholder="Ieškoti užsakymo…" oninput="filterOrders()"></div>
                <button class="btn-xlsx" onclick="toggleExportPanel()"><i class="fa-solid fa-file-excel"></i> XLSX</button>
            </div>
        </div>
        <div id="export-panel" style="display:none;padding:14px 24px;background:#FAFBFC;border-bottom:1px solid var(--border)">
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
                <div>
                    <label style="font-size:11.5px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Nuo</label>
                    <input type="date" class="form-ctrl" id="export-date-from" style="width:160px">
                </div>
                <div>
                    <label style="font-size:11.5px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Iki</label>
                    <input type="date" class="form-ctrl" id="export-date-to" style="width:160px">
                </div>
                <button class="btn btn-solid-blue btn-sm" onclick="exportXlsx('orders')"><i class="fa-solid fa-download"></i> Užsakymai (XLSX)</button>
                <button class="btn btn-solid-green btn-sm" onclick="exportXlsx('invoices')"><i class="fa-solid fa-file-invoice"></i> Sąskaitos faktūros (XLSX)</button>
                <button onclick="toggleExportPanel()" style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:13px;padding:8px">Uždaryti</button>
            </div>
            <div style="display:flex;gap:12px;margin-top:10px">
                <button class="btn btn-gray btn-sm" onclick="exportInvoicesZip('invoice')"><i class="fa-solid fa-file-zipper"></i> PVM sąskaitos (PDF ZIP)</button>
                <button class="btn btn-gray btn-sm" onclick="exportInvoicesZip('credit')"><i class="fa-solid fa-file-zipper"></i> Kreditinės sąskaitos (PDF ZIP)</button>
            </div>
            <p style="font-size:11.5px;color:var(--text3);margin-top:8px">Palikus laukus tuščius, eksportuos visus įrašus (be datos filtro).</p>
        </div>
        <div class="ord-filters">
            <?php
              $pills = array_merge(
                [['v'=>'','name'=>'Visi','count'=>count($orders)]],
                array_map(fn($s)=>['v'=>$s,'name'=>$siFn($s)['lt'],'count'=>count(array_filter($orders,fn($o)=>$o['status']===$s))], $statuses)
              );
              foreach($pills as $idx=>$p): ?>
              <span class="ofilter <?=$idx===0?'active':''?>" data-v="<?=$p['v']?>" onclick="setOrderFilter(this)"><?=$p['name']?> <span style="opacity:.55"><?=$p['count']?></span></span>
            <?php endforeach; ?>
        </div>
        <div style="overflow-x:auto">
        <table id="orders-table">
            <thead><tr>
                <th>Užsakymas</th><th>Klientas</th><th>Prekės</th><th>Suma</th><th>Pristatymas</th><th>Statusas</th><th></th>
            </tr></thead>
            <tbody>
            <?php
            $delMap=['courier'=>['Kurjeris','fa-truck-fast'],'post'=>['Paštomatas','fa-box-archive'],'bus'=>['Autobusas','fa-bus'],'pickup'=>['Atsiėmimas','fa-store']];
            foreach($ordersRev as $o):
                $itemCount=array_sum(array_column($o['cart'],'quantity'));
                $info=$siFn($o['status']);
                $del=$delMap[$o['delivery']??'']??[$o['delivery']??'—','fa-truck'];
            ?>
            <tr data-id="<?=$o['id']?>" data-status="<?=$o['status']?>" data-search="<?=strtolower($o['id'].' '.$o['customer']['name'].' '.$o['customer']['surname'].' '.($o['customer']['email']??'').' '.($o['customer']['phone']??''))?>">
                <td><div style="font-weight:700;color:var(--accent);cursor:pointer;text-decoration:underline;text-underline-offset:2px" onclick="openOrderById('<?=$o['id']?>')" title="Atidaryti užsakymą"><?=$o['id']?></div><div style="font-size:11.5px;color:var(--text3);margin-top:1px"><?=substr($o['date'],0,10)?></div></td>
                <td>
                    <div style="font-weight:600"><?=htmlspecialchars($o['customer']['name'].' '.$o['customer']['surname'])?></div>
                    <div style="font-size:11.5px;color:var(--text3)"><?=htmlspecialchars($o['customer']['phone']??'')?></div>
                </td>
                <td style="color:var(--text2)"><?=$itemCount?> vnt.</td>
                <td style="font-weight:700;font-family:'Sora',sans-serif"><?=htmlspecialchars($o['total'])?></td>
                <td><span style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;color:var(--text2)"><i class="fa-solid <?=$del[1]?>" style="font-size:11px;color:var(--text3)"></i><?=$del[0]?></span></td>
                <td><span class="badge-dot" style="color:<?=$info['c']?>;background:<?=$info['bg']?>"><?=$info['lt']?></span></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="act-btn act-view" onclick='openOrderModal(<?=htmlspecialchars(json_encode($o),ENT_QUOTES)?>)'><i class="fa-solid fa-eye"></i></button>
                        <button class="act-btn act-del" onclick="deleteOrder('<?=$o['id']?>')"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ══ PRODUCTS ══ -->
<div class="panel" id="panel-products">
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Prekių katalogas (<?=count($products)?>)</div>
            <div class="table-actions">
                <input type="text" class="search-box" id="prod-search" placeholder="Ieškoti..." oninput="filterProducts()">
                <button class="btn btn-gray btn-sm" onclick="checkAllImages()" id="check-images-btn"><i class="fa-solid fa-image"></i> Tikrinti nuotraukas</button>
                <button class="btn btn-solid-blue btn-sm" onclick="openProductModal(null)"><i class="fa-solid fa-plus"></i> Nauja prekė</button>
            </div>
        </div>
        <div id="image-check-results" style="padding:0 20px"></div>
        <div style="overflow-x:auto">
        <table id="products-table">
            <thead><tr><th>Nuotr.</th><th>Prekė</th><th>Kodas</th><th>OEM / SKU</th><th>Kaina</th><th>Likutis</th><th>Statusas</th><th>Veiksmai</th></tr></thead>
            <tbody id="products-tbody">
            <?php foreach($products as $p):
                $stock=(int)($p['stock']??0);
                $sc=$stock===0?'stock-out':($stock<=3?'stock-low':'stock-ok');
                $st=$stock===0?'Baigėsi':($stock<=3?'Mažai':'OK');
                $active=($p['active']??1)==1;
                $firstImg = $p['images'][0] ?? '';
                $isExternalImg = preg_match('/^https?:\/\//i', $firstImg);
                $imgSrc = $isExternalImg ? $firstImg : ('uploads/products/'.$firstImg);
            ?>
            <tr data-id="<?=$p['id']?>" data-search="<?=strtolower(($p['name']??'').' '.($p['oem']??'').' '.($p['display_code']??'').' '.($p['brand']??''))?>">
                <td>
                    <?php if(!empty($firstImg)): ?>
                    <img src="<?=htmlspecialchars($imgSrc)?>" class="prod-img" onerror="this.style.display='none'">
                    <?php else: ?><div class="prod-img-ph"><i class="fa-solid fa-image"></i></div><?php endif; ?>
                </td>
                <td>
                    <div style="font-weight:600"><?=htmlspecialchars($p['name']??'')?></div>
                    <div style="font-size:11px;color:#64748b"><?=htmlspecialchars($p['brand']??'')?></div>
                </td>
                <td><span class="badge badge-blue" style="font-family:'Sora',sans-serif">#<?=htmlspecialchars($p['display_code']??'?')?></span></td>
                <td style="font-size:11px;color:#64748b;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($p['oem']??'')?></td>
                <td style="font-weight:700"><?=number_format((float)($p['price']??0),2)?> €</td>
                <td><span class="stock-badge <?=$sc?>"><?=$st?> (<?=$stock?>)</span></td>
                <td><span class="badge <?=$active?'badge-green':'badge-gray'?>"><?=$active?'Aktyvi':'Paslėpta'?></span></td>
                <td>
                    <div style="display:flex;gap:5px">
                        <button class="btn btn-blue btn-sm" onclick='openProductModal(<?=json_encode($p,JSON_HEX_APOS|JSON_HEX_QUOT)?>)'><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-red btn-sm" onclick="deleteProduct('<?=$p['id']?>')"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ══ IMPORT ══ -->
<div class="panel" id="panel-import">
    <div class="panel-header">
        <div class="panel-title">PREKIŲ IMPORTAS</div>
    </div>
    <div class="table-card" style="padding:24px">
        <p style="font-size:13px;color:var(--mid);margin-bottom:16px;line-height:1.6">
            Paleisk <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px">import_runner.py</code>
            Windows serveryje — jis sukurs <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px">ready_for_upload/&lt;SKU&gt;.json</code>
            failus. Tada čia pasirink vieną ar kelis tokius <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px">.json</code>
            failus iš savo kompiuterio ir paspausk "Importuoti".<br>
            <strong>Nuotraukos NEKELIAMOS į serverį</strong> — jos rodomos tiesiai iš Allegro nuorodų (taupo vietą).
        </p>

        <div class="upload-zone" id="import-drop-zone" onclick="document.getElementById('import-file-input').click()" style="margin-bottom:16px">
            <input type="file" id="import-file-input" multiple accept=".json" style="display:none" onchange="handleImportFileSelect(this)">
            <i class="fa-solid fa-file-import" style="font-size:28px;color:#94a3b8;margin-bottom:8px;display:block"></i>
            <p style="font-size:14px;color:#475569;font-weight:600">Spustelėk pasirinkti .json failus</p>
            <p style="font-size:12px;color:#94a3b8;margin-top:4px">Galima pasirinkti vieną arba kelis failus iš karto</p>
        </div>

        <div id="import-selected-files" style="margin-bottom:16px"></div>

        <button class="btn btn-solid-green" onclick="runBrowserImport()" id="run-import-btn" disabled>
            <i class="fa-solid fa-upload"></i> IMPORTUOTI PASIRINKTUS FAILUS
        </button>
        <div id="import-results" style="margin-top:20px"></div>
    </div>
</div>

<!-- ══ CUSTOMERS ══ -->
<div class="panel" id="panel-customers">
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Klientai (<?=count($users)?>)</div>
            <div class="table-actions">
                <input type="text" class="search-box" id="cust-search" placeholder="Ieškoti..." oninput="filterCustomers()">
            </div>
        </div>
        <div style="overflow-x:auto">
        <table id="customers-table">
            <thead><tr><th>Klientas</th><th>El. paštas</th><th>Registracija</th><th>Užsakymai</th><th>Suma</th><th>Veiksmai</th></tr></thead>
            <tbody>
            <?php foreach(array_reverse($users) as $u):
                $userOrders=array_filter($orders,fn($o)=>strtolower($o['customer']['email']??'')===strtolower($u['email']??''));
                $userTotal=array_sum(array_map(fn($o)=>floatval($o['total']),array_filter($userOrders,fn($o)=>$o['status']!=='Cancelled')));
            ?>
            <tr data-search="<?=strtolower(($u['name']??'').' '.($u['email']??''))?>" data-id="<?=htmlspecialchars($u['id']??'')?>">
                <td style="cursor:pointer" onclick='openCustomerModal(<?=json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>, <?=json_encode(array_values($userOrders), JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>)'>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:34px;height:34px;border-radius:50%;background:#FFEDE6;color:#E8431B;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">
                            <?=strtoupper(substr($u['name']??'?',0,1))?>
                        </div>
                        <span style="font-weight:600"><?=htmlspecialchars($u['name']??'')?></span>
                    </div>
                </td>
                <td style="color:#64748b" onclick='openCustomerModal(<?=json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>, <?=json_encode(array_values($userOrders), JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>)' style="cursor:pointer"><?=htmlspecialchars($u['email']??'')?></td>
                <td style="font-size:12px;color:#64748b"><?=substr($u['created']??'',0,10)?></td>
                <td style="font-weight:700"><?=count($userOrders)?></td>
                <td style="font-weight:700;color:var(--accent)"><?=number_format($userTotal,2)?> €</td>
                <td>
                    <button class="btn btn-red btn-sm" onclick="deleteCustomer('<?=htmlspecialchars($u['id']??'')?>', event)"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ══ CUSTOMER DETAIL MODAL ══ -->
<div class="modal-bg" id="customer-modal">
<div class="modal" style="max-width:720px">
    <div class="modal-head">
        <h3 id="customer-modal-title">Kliento informacija</h3>
        <button class="modal-close" onclick="closeModal('customer-modal')">&#x2715;</button>
    </div>
    <div class="modal-body" id="customer-modal-body"></div>
    <div class="modal-footer" style="justify-content:space-between">
        <button class="btn btn-red" onclick="deleteCustomerFromModal()"><i class="fa-solid fa-trash"></i> Ištrinti klientą</button>
        <div style="display:flex;gap:10px">
            <button class="btn btn-gray" onclick="closeModal('customer-modal')">Uždaryti</button>
            <button class="btn btn-solid-blue" onclick="saveCustomerFromModal()"><i class="fa-solid fa-floppy-disk"></i> Išsaugoti</button>
        </div>
    </div>
</div>
</div>

<!-- ══ QUESTIONS ══ -->
<div class="panel" id="panel-questions">
    <?php
    $questionsRev = array_reverse($questions);
    $qNew = count(array_filter($questions, fn($q)=>($q['status']??'Nauja')==='Nauja'));
    $qAns = count($questions) - $qNew;
    if (empty($questionsRev)): ?>
        <div class="dash-card" style="text-align:center;color:var(--text3);padding:48px"><i class="fa-solid fa-comments" style="font-size:30px;display:block;margin-bottom:12px;opacity:.5"></i>Klausimų dar nėra</div>
    <?php else: ?>
    <div class="q-filters">
        <span class="ofilter active" data-v="" onclick="setQFilter(this)">Visi <span style="opacity:.55"><?=count($questions)?></span></span>
        <span class="ofilter" data-v="Nauja" onclick="setQFilter(this)">Neatsakyti <span class="qnew-count"><?=$qNew?></span></span>
        <span class="ofilter" data-v="Atsakyta" onclick="setQFilter(this)">Atsakyti <span style="opacity:.55"><?=$qAns?></span></span>
    </div>
    <div class="q-list" id="questions-list">
        <?php foreach ($questionsRev as $q):
            $isNew = ($q['status'] ?? 'Nauja') === 'Nauja';
            $nm = $q['name'] ?? 'Klientas';
            $ini = strtoupper(mb_substr($nm,0,1).(mb_strpos($nm,' ')!==false?mb_substr($nm,mb_strpos($nm,' ')+1,1):''));
            $hue = crc32($nm) % 360;
            $created = $q['created_at'] ?? '';
        ?>
        <div class="q-card" data-status="<?=htmlspecialchars($q['status']??'Nauja')?>">
            <div class="q-top">
                <div class="q-ava" style="background:linear-gradient(135deg,hsl(<?=$hue?>,62%,58%),hsl(<?=($hue+24)%360?>,62%,48%))"><?=htmlspecialchars($ini)?></div>
                <div style="flex:1;min-width:0">
                    <div class="q-name"><?=htmlspecialchars($nm)?></div>
                    <div class="q-meta"><?=htmlspecialchars($q['product_name']??'Bendras klausimas')?><?= $created? ' · '.htmlspecialchars($created):'' ?><?= $created? ' ('.$relTime($created).')':'' ?></div>
                </div>
                <span class="q-badge <?=$isNew?'new':'answered'?>"><?=$isNew?'Nauja':'Atsakyta'?></span>
                <button class="act-btn act-del" style="margin-left:8px" onclick="deleteQuestion('<?=$q['id']?>')" title="Pašalinti"><i class="fa-solid fa-trash-can"></i></button>
            </div>
            <div class="q-contact">
                <?php if(!empty($q['email'])): ?><span><i class="fa-solid fa-envelope"></i> <a href="mailto:<?=htmlspecialchars($q['email'])?>"><?=htmlspecialchars($q['email'])?></a></span><?php endif; ?>
                <?php if(!empty($q['ip'])): ?><span><i class="fa-solid fa-location-dot"></i> IP: <code><?=htmlspecialchars($q['ip'])?></code></span><?php endif; ?>
                <?php if(!empty($q['answered_at'])): ?><span><i class="fa-solid fa-reply"></i> Atsakyta: <?=htmlspecialchars($q['answered_at'])?></span><?php endif; ?>
            </div>
            <div class="q-text"><?=nl2br(htmlspecialchars($q['message']??''))?></div>
            <?php if(!$isNew && !empty($q['answer'])): $ans=$q['answer']; $ansHtml=(strip_tags($ans)!==$ans)?$ans:nl2br(htmlspecialchars($ans)); ?>
            <div class="q-answer-old"><strong style="color:#1F8A5B">Jūsų atsakymas:</strong><br><?=$ansHtml?></div>
            <?php endif; ?>
            <div class="q-reply-wrap">
                <div class="q-toolbar">
                    <button type="button" onclick="qFormat('bold')" title="Paryškinti"><i class="fa-solid fa-bold"></i></button>
                    <button type="button" onclick="qFormat('italic')" title="Kursyvas"><i class="fa-solid fa-italic"></i></button>
                    <button type="button" onclick="qFormat('underline')" title="Pabraukti"><i class="fa-solid fa-underline"></i></button>
                    <button type="button" onclick="qFormat('insertUnorderedList')" title="Sąrašas"><i class="fa-solid fa-list-ul"></i></button>
                    <button type="button" onclick="qInsertLink('<?=$q['id']?>')" title="Nuoroda"><i class="fa-solid fa-link"></i></button>
                    <button type="button" onclick="document.getElementById('qimg-<?=$q['id']?>').click()" title="Įterpti paveikslėlį"><i class="fa-solid fa-image"></i></button>
                    <input type="file" id="qimg-<?=$q['id']?>" accept="image/*" style="display:none" onchange="qInsertImage(this,'<?=$q['id']?>')">
                </div>
                <div class="q-editor" id="answer-<?=$q['id']?>" contenteditable="true" data-ph="Rašyti atsakymą… (galite įklijuoti tekstą su formatavimu ir paveikslėliais)"></div>
                <div style="display:flex;justify-content:flex-end;margin-top:10px">
                    <button class="q-send-btn" onclick="answerQuestion('<?=$q['id']?>')"><i class="fa-solid fa-paper-plane"></i> Siųsti atsakymą</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ══ SETTINGS ══ -->
<div class="panel" id="panel-settings">
    <div class="settings-section">
        <div class="settings-head"><i class="fa-solid fa-tags" style="color:var(--accent)"></i> Kainodara — antkainis ir nuolaida</div>
        <div class="settings-body">
            <p style="font-size:12.5px;color:#64748b;margin-bottom:14px;line-height:1.6">
                Antkainis pridedamas prie importuotos (bazinės) prekės kainos. Nuolaida rodoma kaip perbraukta
                „senoji" kaina virš galutinės — pirkėjui atrodo, kad prekė parduodama su nuolaida.<br>
                <strong>Pvz.:</strong> bazinė kaina 100€, antkainis 0%, nuolaida 20% → rodoma kaina <strong>100€</strong>,
                perbraukta senoji kaina <strong>120€</strong>.
            </p>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Bendras antkainis visoms prekėms (%)</label>
                    <input class="form-ctrl" id="s-default-markup" type="number" step="0.1" value="<?=htmlspecialchars($settings['default_markup_percent']??0)?>" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Nuolaidos procentas (%)</label>
                    <input class="form-ctrl" id="s-default-discount" type="number" step="0.1" value="<?=htmlspecialchars($settings['default_discount_percent']??0)?>" placeholder="0">
                </div>
            </div>

            <div style="margin-top:18px;padding-top:16px;border-top:1px solid #f1f5f9">
                <div style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
                    Antkainis pagal kainos diapazoną (didžiausias prioritetas)
                </div>
                <p style="font-size:12px;color:#94a3b8;margin-bottom:10px">Pvz.: prekėms nuo 1€ iki 20€ taikomas vienas %, prekėms nuo 20€ iki 50€ — kitas. Šis antkainis VISADA viršija kategorijos ar bendrą antkainį, jei bazinė kaina patenka į intervalą.</p>
                <div id="price-range-markup-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:10px"></div>
                <div style="display:flex;gap:8px;align-items:center">
                    <input class="form-ctrl" id="new-range-min" type="number" step="0.01" placeholder="Nuo €" style="width:90px">
                    <span style="color:#94a3b8">—</span>
                    <input class="form-ctrl" id="new-range-max" type="number" step="0.01" placeholder="Iki €" style="width:90px">
                    <input class="form-ctrl" id="new-range-percent" type="number" step="0.1" placeholder="%" style="width:90px">
                    <button class="btn btn-blue" onclick="addPriceRangeMarkup()"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>

            <div style="margin-top:18px;padding-top:16px;border-top:1px solid #f1f5f9">
                <div style="font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">
                    Antkainis pagal kategoriją (pakeičia bendrą antkainį tai kategorijai)
                </div>
                <div id="category-markup-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:10px"></div>
                <div style="display:flex;gap:8px">
                    <select class="form-ctrl" id="new-markup-category" style="flex:1"></select>
                    <input class="form-ctrl" id="new-markup-percent" type="number" step="0.1" placeholder="%" style="width:90px">
                    <button class="btn btn-blue" onclick="addCategoryMarkup()"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>
        </div>
    </div>
    <div class="settings-section">
        <div class="settings-head"><i class="fa-solid fa-envelope" style="color:var(--accent)"></i> El. pašto nustatymai (PHPMailer / Gmail)</div>
        <div class="settings-body">
            <p style="font-size:12.5px;color:#94a3b8;margin-bottom:14px;line-height:1.6">
                Naudoji <strong>Gmail App Password</strong> (16 simbolių kodas), NE savo įprastą Gmail slaptažodį.
                Jį gauni: Google paskyra → Saugumas → 2 žingsnių patvirtinimas (turi būti įjungtas) → Programų slaptažodžiai.
                Įvesk kodą BE tarpų arba su tarpais — abu variantai veiks.
            </p>
            <div class="form-grid">
                <div class="form-group"><label class="form-label">Gmail adresas</label><input class="form-ctrl" id="s-gmail-user" value="<?=htmlspecialchars($settings['gmail_user']??'')?>" placeholder="jusu@gmail.com"></div>
                <div class="form-group">
                    <label class="form-label">App Password</label>
                    <div style="position:relative">
                        <input class="form-ctrl" id="s-gmail-pass" type="password" value="<?=htmlspecialchars($settings['gmail_pass']??'')?>" placeholder="xxxx xxxx xxxx xxxx" style="padding-right:40px">
                        <button type="button" onclick="toggleGmailPassVisibility()" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer" id="gmail-pass-toggle-btn"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>
                <div class="form-group"><label class="form-label">Admin el. paštas (gauna pranešimus)</label><input class="form-ctrl" id="s-admin-email" value="<?=htmlspecialchars($settings['admin_email']??'')?>" placeholder="admin@example.com"></div>
            </div>
            <button class="btn btn-gray btn-sm" style="margin-top:10px" onclick="testGmailConnection()" id="test-gmail-btn"><i class="fa-solid fa-paper-plane"></i> Testuoti prisijungimą</button>
            <div id="test-gmail-result" style="margin-top:10px"></div>
        </div>
    </div>
    <div class="settings-section">
        <div class="settings-head"><i class="fa-solid fa-globe" style="color:var(--accent)"></i> Svetainės nustatymai</div>
        <div class="settings-body">
            <div class="form-grid">
                <div class="form-group"><label class="form-label">Svetainės URL</label><input class="form-ctrl" id="s-site-url" value="<?=htmlspecialchars($settings['site_url']??'')?>" placeholder="https://pneumatinepagalve.lt"></div>
                <div class="form-group"><label class="form-label">Svetainės pavadinimas (logotipas)</label><input class="form-ctrl" id="s-site-name" value="<?=htmlspecialchars($settings['site_name']??'market')?>" placeholder="market"></div>
            </div>
        </div>
    </div>
    <div class="settings-section">
        <div class="settings-head"><i class="fa-solid fa-heading" style="color:var(--accent)"></i> Pradžios puslapio tekstas (Hero)</div>
        <div class="settings-body">
            <p style="font-size:12.5px;color:#94a3b8;margin-bottom:14px">Tekstas, rodomas didžiajame bloke pradžios puslapio viršuje. Skirtingas tekstas kiekvienai kalbai.</p>
            <div style="display:flex;gap:4px;margin-bottom:14px;border-bottom:1px solid #f1f5f9">
                <?php $heroLangs = ['lt'=>'LT','en'=>'EN','ru'=>'RU','lv'=>'LV','et'=>'ET','fi'=>'FI']; $first = true; ?>
                <?php foreach($heroLangs as $code => $label): ?>
                <button type="button" class="hero-lang-tab-btn<?=$first?' active':''?>" data-lang="<?=$code?>" onclick="switchHeroLangTab('<?=$code?>')" style="padding:8px 16px;background:none;border:none;border-bottom:2px solid <?=$first?'var(--accent)':'transparent'?>;color:<?=$first?'var(--accent)':'#64748b'?>;font-weight:600;font-size:13px;cursor:pointer"><?=$label?></button>
                <?php $first = false; endforeach; ?>
            </div>
            <?php $defaultEyebrows = ['lt'=>'LIETUVOS EL. PARDUOTUVĖ','en'=>'LITHUANIAN ONLINE STORE','ru'=>'ЛИТОВСКИЙ ИНТЕРНЕТ-МАГАЗИН','lv'=>'LIETUVAS INTERNETA VEIKALS','et'=>'LEEDU E-POOD','fi'=>'LIETTUAN VERKKOKAUPPA'];
                  $defaultTitles = ['lt'=>'Viskas, ko reikia, vienoje vietoje','en'=>'Everything you need, in one place','ru'=>'Всё, что вам нужно, в одном месте','lv'=>'Viss, kas nepieciešams, vienā vietā','et'=>'Kõik, mida vajate, ühes kohas','fi'=>'Kaikki tarvittava, yhdessä paikassa'];
                  $first = true; ?>
            <?php foreach($heroLangs as $code => $label): ?>
            <div class="hero-lang-panel" data-lang="<?=$code?>" style="display:<?=$first?'block':'none'?>">
                <div class="form-grid">
                    <div class="form-group full"><label class="form-label">Mažas tekstas viršuje (eyebrow)</label><input class="form-ctrl hero-eyebrow-input" data-lang="<?=$code?>" value="<?=htmlspecialchars($settings['hero_eyebrow'][$code] ?? $defaultEyebrows[$code])?>"></div>
                    <div class="form-group full"><label class="form-label">Pagrindinė antraštė</label><input class="form-ctrl hero-title-input" data-lang="<?=$code?>" value="<?=htmlspecialchars($settings['hero_title'][$code] ?? $defaultTitles[$code])?>"></div>
                </div>
            </div>
            <?php $first = false; endforeach; ?>
        </div>
    </div>
    <div class="settings-section">
        <div class="settings-head"><i class="fa-solid fa-lock" style="color:var(--accent)"></i> Slaptažodžio keitimas</div>
        <div class="settings-body">
            <p style="font-size:13px;color:#64748b;margin-bottom:12px">Norėdami pakeisti admin slaptažodį, atsidarykite <strong>admin.php</strong> failą ir pakeiskite <code>$ADMIN_PASS</code> kintamąjį.</p>
        </div>
    </div>
    <button class="btn btn-solid-blue" onclick="saveSettings()"><i class="fa-solid fa-floppy-disk"></i> Išsaugoti nustatymus</button>
</div>

</div><!-- end content -->
</div><!-- end main -->
</div><!-- end layout -->

<!-- ══ ORDER MODAL ══ -->
<div class="modal-bg" id="order-modal">
<div class="modal" style="max-width:760px">
    <div class="modal-head">
        <h3 id="order-modal-title">Užsakymas</h3>
        <button class="modal-close" onclick="closeModal('order-modal')">&#x2715;</button>
    </div>
    <div class="modal-body" id="order-modal-body"></div>
    <div class="modal-footer" style="justify-content:space-between">
        <button class="btn btn-red" onclick="quickStatusChange('Cancelled')"><i class="fa-solid fa-ban"></i> Atšaukti užsakymą</button>
        <div style="display:flex;gap:10px">
            <button class="btn btn-gray" onclick="closeModal('order-modal')">Uždaryti</button>
            <button class="btn btn-solid-blue" onclick="saveOrderFromModal()"><i class="fa-solid fa-floppy-disk"></i> Išsaugoti</button>
        </div>
    </div>
</div>
</div>

<!-- ══ PRODUCT MODAL ══ -->
<div class="modal-bg" id="product-modal">
<div class="modal" style="max-width:640px">
    <div class="modal-head">
        <h3 id="product-modal-title">Nauja prekė</h3>
        <button class="modal-close" onclick="closeModal('product-modal')">&#x2715;</button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="pm-id">
        <div class="form-grid">
            <div class="form-group full"><label class="form-label">Pavadinimas</label><input class="form-ctrl full" id="pm-name" placeholder="BMW F11 Pneumatinė pagalvė"></div>
            <div class="form-group"><label class="form-label">Kategorija</label>
                <select class="form-ctrl" id="pm-category-parent" onchange="updateSubCategoryOptions()"></select>
            </div>
            <div class="form-group"><label class="form-label">Pogrupis</label>
                <select class="form-ctrl" id="pm-category-sub"></select>
            </div>
            <div class="form-group"><label class="form-label">Gamintojas (Brand)</label><input class="form-ctrl" id="pm-brand" placeholder="OEM, Arnott..."></div>
            <div class="form-group"><label class="form-label">Kaina (€)</label><input class="form-ctrl" id="pm-price" type="number" step="0.01" placeholder="50.00"></div>
            <div class="form-group"><label class="form-label">Likutis (vnt.)</label><input class="form-ctrl" id="pm-stock" type="number" placeholder="10"></div>
            <div class="form-group"><label class="form-label">Statusas</label>
                <select class="form-ctrl" id="pm-active"><option value="1">Aktyvi (rodoma)</option><option value="0">Paslėpta</option></select>
            </div>
            <div class="form-group full"><label class="form-label">OEM Kodai</label><input class="form-ctrl full" id="pm-oem" placeholder="37106781827, 37106781843..."></div>
            <div class="form-group full"><label class="form-label">Aprašymas</label><textarea class="form-ctrl full" id="pm-desc" placeholder="Prekės aprašymas..."></textarea></div>
            <div class="form-group full"><label class="form-label">Tinkamumas (modeliai)</label><textarea class="form-ctrl full" id="pm-compat" placeholder="BMW F11: 520d, 525d..."></textarea></div>
        </div>
        <!-- Images -->
        <div style="margin-top:8px">
            <label class="form-label">Nuotraukos</label>
            <div class="upload-zone" onclick="document.getElementById('pm-img-input').click()">
                <input type="file" id="pm-img-input" multiple accept="image/*" style="display:none" onchange="handleImgUpload(this)">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size:24px;color:#94a3b8;margin-bottom:6px;display:block"></i>
                <p style="font-size:13px;color:#64748b">Spustelėkite įkelti nuotraukas</p>
                <p style="font-size:11px;color:#94a3b8">JPG, PNG, WEBP</p>
            </div>
            <div class="img-preview-grid" id="pm-images"></div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-gray" onclick="closeModal('product-modal')">Atšaukti</button>
        <button class="btn btn-solid-blue" onclick="saveProduct()"><i class="fa-solid fa-floppy-disk"></i> Išsaugoti</button>
    </div>
</div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
// ── PANEL SWITCH ──────────────────────────────────────────
function showPanel(name, el) {
    document.querySelectorAll('.panel').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
    document.getElementById('panel-'+name).classList.add('active');
    if(el) el.classList.add('active');
    const titles={dashboard:'Konsolė',orders:'Užsakymai',products:'Prekės',import:'Importas',customers:'Klientai',questions:'Klausimai',settings:'Nustatymai'};
    document.getElementById('topbar-title').textContent=titles[name]||name;
    if(window.innerWidth<768) document.getElementById('sidebar').classList.remove('open');
}

let selectedImportFiles = [];

function handleImportFileSelect(input){
    selectedImportFiles = Array.from(input.files);
    renderSelectedImportFiles();
}

function renderSelectedImportFiles(){
    const el = document.getElementById('import-selected-files');
    const btn = document.getElementById('run-import-btn');
    if(!selectedImportFiles.length){
        el.innerHTML = '';
        btn.disabled = true;
        return;
    }
    btn.disabled = false;
    el.innerHTML = `<div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Pasirinkta ${selectedImportFiles.length} failų:</div>` +
        selectedImportFiles.map((f,i)=>`
            <div style="display:flex;justify-content:space-between;align-items:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;margin-bottom:6px;font-size:13px">
                <span><i class="fa-solid fa-file-code" style="color:#94a3b8;margin-right:8px"></i>${f.name}</span>
                <button onclick="removeImportFile(${i})" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:13px"><i class="fa-solid fa-xmark"></i></button>
            </div>`).join('');
}

function removeImportFile(i){
    selectedImportFiles.splice(i,1);
    renderSelectedImportFiles();
}

async function runBrowserImport(){
    if(!selectedImportFiles.length) return;
    const btn = document.getElementById('run-import-btn');
    const resultsEl = document.getElementById('import-results');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importuojama...';
    resultsEl.innerHTML = '';

    const formData = new FormData();
    selectedImportFiles.forEach(f => formData.append('json_files[]', f));

    try{
        const res = await fetch('admin.php?ajax=1&action=upload_import_json', {method:'POST', body:formData});
        const data = await res.json();
        if(!data.ok){
            resultsEl.innerHTML = `<div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:8px;font-size:13px">${data.msg || 'Importo klaida.'}</div>`;
        } else {
            resultsEl.innerHTML = data.results.map(r=>`
                <div style="background:${r.ok?'#dcfce7':'#fee2e2'};color:${r.ok?'#166534':'#991b1b'};padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:8px">
                    <strong>${r.sku}</strong> — ${r.msg}
                </div>`).join('');
            toast('Importas baigtas!');
            selectedImportFiles = [];
            document.getElementById('import-file-input').value = '';
            renderSelectedImportFiles();
        }
    }catch(e){
        resultsEl.innerHTML = `<div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:8px;font-size:13px">Serverio klaida importuojant.</div>`;
    }
    btn.disabled = selectedImportFiles.length === 0;
    btn.innerHTML = '<i class="fa-solid fa-upload"></i> IMPORTUOTI PASIRINKTUS FAILUS';
}

// ── FILTERS ───────────────────────────────────────────────
let orderFilterVal='';
function setOrderFilter(el){
    orderFilterVal = el.dataset.v;
    document.querySelectorAll('.ofilter').forEach(p=>p.classList.remove('active'));
    el.classList.add('active');
    filterOrders();
}
function filterOrders(){
    const q=(document.getElementById('orders-search').value||'').toLowerCase();
    const s=orderFilterVal;
    document.querySelectorAll('#orders-table tbody tr').forEach(r=>{
        const match=(!q||r.dataset.search.includes(q))&&(!s||r.dataset.status===s);
        r.style.display=match?'':'none';
    });
}
function filterProducts(){
    const q=document.getElementById('prod-search').value.toLowerCase();
    document.querySelectorAll('#products-table tbody tr').forEach(r=>{
        r.style.display=(!q||r.dataset.search.includes(q))?'':'none';
    });
}
function filterCustomers(){
    const q=document.getElementById('cust-search').value.toLowerCase();
    document.querySelectorAll('#customers-table tbody tr').forEach(r=>{
        r.style.display=(!q||r.dataset.search.includes(q))?'':'none';
    });
}

// ── MODALS ────────────────────────────────────────────────
function openModal(id){ document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id){ document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }

// ── ORDER MODAL ───────────────────────────────────────────
let currentOrder = null;
const statuses = <?= json_encode($statuses) ?>;
const bc = {Submitted:'badge-blue',Confirmed:'badge-yellow','Processed':'badge-purple',Completed:'badge-green','Cancelled':'badge-red'};
const delLabels = {courier:'Kurjeriu',post:'Paštomatas',bus:'Autobusų siuntos'};
const stepperStatuses = ['Submitted','Confirmed','Processed','Completed'];

const ordersData = <?= json_encode(array_values($ordersRev), JSON_UNESCAPED_UNICODE) ?>;
function openOrderById(id){
    const o = ordersData.find(x=>x.id===id);
    if(o) openOrderModal(o); else toast('Užsakymas nerastas');
}
// Paveikslėlio padidinimas (lightbox)
function zoomImage(src){
    if(!src) return;
    let lb = document.getElementById('img-lightbox');
    if(!lb){
        lb = document.createElement('div');
        lb.id = 'img-lightbox';
        lb.style.cssText = 'position:fixed;inset:0;background:rgba(11,25,41,.88);z-index:9999;display:flex;align-items:center;justify-content:center;padding:30px;cursor:zoom-out';
        lb.onclick = ()=>lb.remove();
        document.body.appendChild(lb);
    }
    lb.innerHTML = `<img src="${src}" style="max-width:92%;max-height:92%;border-radius:14px;box-shadow:0 30px 80px rgba(0,0,0,.6)">`;
    lb.style.display='flex';
}
// Prekės išėmimas iš prekybos (stock = 0)
async function delistProduct(id, sku, name){
    if(!confirm('Išimti „'+name+'" iš prekybos? (likutis taps 0)')) return;
    try{
        const r = await fetch('admin.php?ajax=1&action=delist_product',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,sku})});
        const d = await r.json();
        if(d.ok){ toast('Prekė išimta iš prekybos'); } else { toast('Prekė nerasta kataloge'); }
    }catch(e){ toast('Klaida'); }
}

function openOrderModal(order){
    currentOrder = order;
    document.getElementById('order-modal-title').innerHTML = `Užsakymas <span style="color:var(--accent)">${order.id}</span> <span class="badge ${bc[order.status]||'badge-gray'}" style="margin-left:8px">${order.status}</span>`;

    const isCancelled = order.status === 'Cancelled';
    const curStepIdx = stepperStatuses.indexOf(order.status);

    const stepperHtml = isCancelled ? `
        <div style="background:#fee2e2;color:#991b1b;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:16px;text-align:center">
            <i class="fa-solid fa-ban"></i> Šis užsakymas atšauktas
        </div>` : `
        <div style="display:flex;align-items:center;margin-bottom:20px;padding:0 4px">
            ${stepperStatuses.map((s,i)=>`
                <div style="display:flex;flex-direction:column;align-items:center;flex:1">
                    <div style="width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;background:${i<=curStepIdx?'#FF5A33':'#e2e8f0'};color:${i<=curStepIdx?'white':'#94a3b8'}">
                        ${i<curStepIdx?'<i class="fa-solid fa-check"></i>':(i+1)}
                    </div>
                    <div style="font-size:10.5px;font-weight:600;margin-top:5px;color:${i<=curStepIdx?'#FF5A33':'#94a3b8'}">${s}</div>
                </div>
                ${i<stepperStatuses.length-1?`<div style="flex:1;height:2px;background:${i<curStepIdx?'#FF5A33':'#e2e8f0'};margin-bottom:18px"></div>`:''}`
            ).join('')}
        </div>`;

    const itemsHtml = order.cart.map(i=>{
        const img = i.img||'';
        const safeName = (i.name||'').replace(/[\\'"]/g,' ');
        const imgHtml = img
            ? `<img src="${img}" onclick="zoomImage('${img}')" onerror="this.onerror=null;this.removeAttribute('src');this.style.cursor='default'" style="width:56px;height:56px;object-fit:cover;border-radius:11px;border:1px solid #e9ecf1;background:#F2F4F7;cursor:zoom-in;flex-shrink:0">`
            : `<div style="width:56px;height:56px;border-radius:11px;background:#F2F4F7;display:flex;align-items:center;justify-content:center;color:#94A4B5;flex-shrink:0"><i class="fa-solid fa-image"></i></div>`;
        return `
        <div style="display:flex;align-items:center;gap:12px;font-size:13px;padding:10px 0;border-bottom:1px solid #f1f5f9">
            ${imgHtml}
            <div style="flex:1;min-width:0">
                <div style="font-weight:600">${i.name} <span style="color:#94a3b8">x${i.quantity}</span></div>
                ${i.sku?`<div style="font-size:11px;color:#94a3b8;margin-top:2px">SKU: <code style="background:#f1f5f9;padding:1px 5px;border-radius:4px">${i.sku}</code></div>`:''}
                <button onclick="delistProduct('${i.id||''}','${i.sku||''}','${safeName}')" style="margin-top:7px;display:inline-flex;align-items:center;gap:5px;background:#FEF0F3;color:#E11D48;border:none;border-radius:8px;padding:5px 11px;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit"><i class="fa-solid fa-eye-slash"></i> Išimti iš prekybos</button>
            </div>
            <span style="font-weight:700;white-space:nowrap">${(i.price*i.quantity).toFixed(2)} €</span>
        </div>`;
    }).join('');

    document.getElementById('order-modal-body').innerHTML = `
        ${stepperHtml}
        <div class="order-detail-grid">
            <div class="detail-box">
                <div class="detail-label">Klientas</div>
                <div class="detail-val">
                    <strong>${order.customer.name} ${order.customer.surname}</strong><br>
                    ${order.customer.phone}<br>
                    ${order.customer.email||''}<br>
                    ${order.delivery==='post' && order.terminal
                        ? `<strong>Paštomatas:</strong> ${order.terminal.name}`
                        : `${order.customer.city}, ${order.customer.street}<br>${order.customer.zipcode}`}
                </div>
            </div>
            <div class="detail-box">
                <div class="detail-label">Informacija</div>
                <div class="detail-val">
                    <strong>Nr.:</strong> ${order.id}<br>
                    <strong>Data:</strong> ${order.date}<br>
                    <strong>Pristatymas:</strong> ${delLabels[order.delivery]||order.delivery}${order.ip?`<br><strong>IP:</strong> <code style="background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:11px">${order.ip}</code>`:''}
                    ${order.invoice ? `<br><br><div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:8px 10px;margin-top:4px">
                        <i class="fa-solid fa-file-invoice" style="color:#16a34a;margin-right:4px"></i><strong>PVM sąskaita:</strong><br>
                        ${order.invoice.company_name}<br>
                        Kodas: ${order.invoice.company_code}${order.invoice.vat_code?` · PVM: ${order.invoice.vat_code}`:''}<br>
                        ${order.invoice.company_address}<br>
                        <a href="invoices/invoice_${order.id}.pdf" target="_blank" style="color:#FF5A33;font-weight:600;font-size:12px"><i class="fa-solid fa-download"></i> Atsisiųsti PDF</a>
                    </div>` : ''}
                </div>
            </div>
        </div>
        <div style="margin-bottom:16px">
            <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Prekės</div>
            ${itemsHtml}
            <div style="display:flex;justify-content:space-between;font-weight:700;padding:8px 0;font-size:14px">
                <span>Viso:</span><span style="color:var(--accent)">${order.total}</span>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Statusas</label>
                <select class="form-ctrl" id="om-status">
                    ${statuses.map(s=>`<option value="${s}"${order.status===s?' selected':''}>${s}</option>`).join('')}
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Sekimo numeris</label>
                <input class="form-ctrl" id="om-tracking" value="${order.tracking||''}" placeholder="LP000000000LT">
            </div>
            <div class="form-group full">
                <label class="form-label">Pastabos (vidinės)</label>
                <textarea class="form-ctrl full" id="om-notes" placeholder="Vidinės pastabos...">${order.notes||''}</textarea>
            </div>
        </div>`;
    openModal('order-modal');
}

async function quickStatusChange(newStatus){
    if(!currentOrder) return;
    if(newStatus==='Cancelled' && !confirm('Ar tikrai norite atšaukti šį užsakymą?')) return;
    document.getElementById('om-status').value = newStatus;
    await saveOrderFromModal();
}

async function saveOrderFromModal(){
    const body={id:currentOrder.id, status:document.getElementById('om-status').value, tracking:document.getElementById('om-tracking').value, notes:document.getElementById('om-notes').value};
    await fetch('admin.php?ajax=1&action=update_order',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    toast('Išsaugota!'); closeModal('order-modal');
    // Update row badge
    const row=document.querySelector(`tr[data-id="${currentOrder.id}"]`);
    if(row){ row.querySelector('.badge').className='badge '+(bc[body.status]||'badge-gray'); row.querySelector('.badge').textContent=body.status; row.dataset.status=body.status; }
}

async function deleteOrder(id){
    if(!confirm('Ar tikrai ištrinti užsakymą '+id+'?')) return;
    await fetch('admin.php?ajax=1&action=delete_order',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    document.querySelector(`tr[data-id="${id}"]`)?.remove();
    toast('Užsakymas ištrintas');
}

// ── QUESTIONS ─────────────────────────────────────────────
function filterQuestions(){ /* pakeista piliulėmis (setQFilter) */ }

// ── CUSTOMER DETAIL MODAL ─────────────────────────────────
let currentCustomer = null;
const statusColorsLT = {Submitted:'badge-blue',Confirmed:'badge-yellow',Processed:'badge-purple',Completed:'badge-green',Cancelled:'badge-red'};

function openCustomerModal(user, userOrders){
    currentCustomer = user;
    document.getElementById('customer-modal-title').textContent = 'Klientas: ' + (user.name || user.email);

    const addresses = user.addresses || [];
    const ips = [...new Set(userOrders.map(o=>o.ip).filter(Boolean))];

    const ordersHtml = userOrders.length ? userOrders.slice().reverse().map(o=>`
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;margin-bottom:8px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <span style="font-weight:700;color:var(--accent);font-size:13px">${o.id}</span>
                <span class="badge ${statusColorsLT[o.status]||'badge-gray'}">${o.status}</span>
            </div>
            <div style="font-size:12px;color:#64748b">${o.date} ${o.ip?`· IP: <code style="background:#fff;padding:1px 5px;border-radius:4px">${o.ip}</code>`:''}</div>
            <div style="font-size:12px;color:#64748b;margin-top:4px">${(o.cart||[]).map(i=>`${i.name} x${i.quantity}`).join(', ')}</div>
            <div style="font-size:13px;font-weight:700;margin-top:4px">${o.total}</div>
        </div>`).join('') : '<p style="color:#94a3b8;font-size:13px">Užsakymų dar nėra.</p>';

    const addressesHtml = addresses.length ? addresses.map(a=>`
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;margin-bottom:8px;font-size:12.5px">
            <strong>${a.label}</strong><br>${a.name} ${a.surname}<br>${a.street}, ${a.city} ${a.zipcode}${a.phone?`<br>${a.phone}`:''}
        </div>`).join('') : '<p style="color:#94a3b8;font-size:13px">Išsaugotų adresų nėra.</p>';

    document.getElementById('customer-modal-body').innerHTML = `
        <div class="form-grid" style="margin-bottom:18px">
            <div class="form-group"><label class="form-label">Vardas</label><input class="form-ctrl" id="cm-name" value="${(user.name||'').replace(/"/g,'&quot;')}"></div>
            <div class="form-group"><label class="form-label">El. paštas</label><input class="form-ctrl" id="cm-email" value="${user.email||''}" disabled style="background:#f1f5f9"></div>
            <div class="form-group"><label class="form-label">Registruota</label><input class="form-ctrl" value="${user.created||''}" disabled style="background:#f1f5f9"></div>
            <div class="form-group"><label class="form-label">Patvirtinta el. paštas</label><input class="form-ctrl" value="${user.verified===false?'Ne':'Taip'}" disabled style="background:#f1f5f9"></div>
        </div>

        <div style="margin-bottom:18px">
            <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">
                IP adresai (iš užsakymų) ${ips.length?`<span class="badge badge-gray">${ips.length}</span>`:''}
            </div>
            ${ips.length ? `<div style="display:flex;gap:6px;flex-wrap:wrap">${ips.map(ip=>`<code style="background:#f1f5f9;padding:3px 8px;border-radius:6px;font-size:12px">${ip}</code>`).join('')}</div>` : '<p style="color:#94a3b8;font-size:13px">Nėra IP duomenų.</p>'}
        </div>

        <div style="margin-bottom:18px">
            <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Išsaugoti adresai</div>
            ${addressesHtml}
        </div>

        <div>
            <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Užsakymų istorija (${userOrders.length})</div>
            <div style="max-height:280px;overflow-y:auto">${ordersHtml}</div>
        </div>`;

    openModal('customer-modal');
}

async function saveCustomerFromModal(){
    if(!currentCustomer) return;
    const newName = document.getElementById('cm-name').value.trim();
    if(!newName){ alert('Vardas negali būti tuščias!'); return; }
    await fetch('admin.php?ajax=1&action=update_customer', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id: currentCustomer.id, name: newName})
    });
    toast('Kliento informacija atnaujinta!');
    closeModal('customer-modal');
    setTimeout(()=>location.reload(), 600);
}

async function deleteCustomer(id, event){
    if(event) event.stopPropagation();
    if(!confirm('Ar tikrai ištrinti šio kliento paskyrą? Jo užsakymų istorija LIKS sistemoje, bet paskyra bus pašalinta.')) return;
    await fetch('admin.php?ajax=1&action=delete_customer', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id})});
    toast('Klientas ištrintas');
    setTimeout(()=>location.reload(), 600);
}

async function deleteCustomerFromModal(){
    if(!currentCustomer) return;
    if(!confirm('Ar tikrai ištrinti šio kliento paskyrą? Jo užsakymų istorija LIKS sistemoje, bet paskyra bus pašalinta.')) return;
    await fetch('admin.php?ajax=1&action=delete_customer', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id: currentCustomer.id})});
    toast('Klientas ištrintas');
    closeModal('customer-modal');
    setTimeout(()=>location.reload(), 600);
}

async function answerQuestion(id){
    const ed = document.getElementById('answer-'+id);
    const answer = ed.innerHTML.trim();
    const plain = ed.textContent.trim();
    if(!plain && !ed.querySelector('img')){ alert('Įveskite atsakymo tekstą!'); return; }
    await fetch('admin.php?ajax=1&action=answer_question',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,answer})});
    toast('Atsakymas išsiųstas!');
    setTimeout(()=>location.reload(),600);
}
// Klausimų filtras (piliulės)
let qFilterVal='';
function setQFilter(el){
    qFilterVal = el.dataset.v;
    document.querySelectorAll('.q-filters .ofilter').forEach(p=>p.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('#questions-list .q-card').forEach(c=>{
        c.style.display = (!qFilterVal || c.dataset.status===qFilterVal) ? '' : 'none';
    });
}
// Atsakymo redaktoriaus įrankiai
function qFormat(cmd){ document.execCommand(cmd,false,null); }
function qInsertLink(id){ const u=prompt('Nuorodos URL (su https://):'); if(u){ document.getElementById('answer-'+id).focus(); document.execCommand('createLink',false,u);} }
function qInsertImage(input,id){
    const f=input.files[0]; if(!f) return;
    if(f.size>3*1024*1024){ alert('Paveikslėlis per didelis (maks. 3 MB).'); input.value=''; return; }
    const r=new FileReader();
    r.onload=e=>{ const ed=document.getElementById('answer-'+id); ed.focus(); document.execCommand('insertImage',false,e.target.result); };
    r.readAsDataURL(f); input.value='';
}

async function deleteQuestion(id){
    if(!confirm('Ištrinti šį klausimą?')) return;
    await fetch('admin.php?ajax=1&action=delete_question',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    document.querySelector(`.question-card[data-id="${id}"]`)?.remove();
    toast('Klausimas ištrintas');
    setTimeout(()=>location.reload(),400);
}

// ── PRODUCT MODAL ─────────────────────────────────────────
let pmImages = [];

function openProductModal(prod){
    pmImages = prod?.images ? [...prod.images] : [];
    document.getElementById('pm-id').value     = prod?.id||'';
    document.getElementById('pm-name').value   = prod?.name||'';
    document.getElementById('pm-brand').value  = prod?.brand||'';
    document.getElementById('pm-price').value  = prod?.price||'';
    document.getElementById('pm-stock').value  = prod?.stock??'';
    document.getElementById('pm-oem').value    = prod?.oem||'';
    document.getElementById('pm-desc').value   = prod?.desc||'';
    document.getElementById('pm-compat').value = prod?.compat||'';
    document.getElementById('pm-active').value = prod?.active===0?'0':'1';
    document.getElementById('product-modal-title').textContent = prod?'Redaguoti prekę':'Nauja prekė';
    populateCategorySelect(prod?.category_parent, prod?.category_sub);
    renderImgPreview();
    openModal('product-modal');
}

let categoriesTreeData = null;
async function loadCategoriesTree(){
    if(categoriesTreeData) return categoriesTreeData;
    const res = await fetch('get_categories.php');
    const data = await res.json();
    categoriesTreeData = data.ok ? data.categories : [];
    return categoriesTreeData;
}

async function populateCategorySelect(selectedParent, selectedSub){
    const tree = await loadCategoriesTree();
    const parentSel = document.getElementById('pm-category-parent');
    parentSel.innerHTML = tree.map(c=>`<option value="${c.name}">${c.name}</option>`).join('');
    if(selectedParent) parentSel.value = selectedParent;
    updateSubCategoryOptions(selectedSub);
}

function updateSubCategoryOptions(selectedSub){
    const parentName = document.getElementById('pm-category-parent').value;
    const cat = (categoriesTreeData||[]).find(c=>c.name===parentName);
    const subSel = document.getElementById('pm-category-sub');
    const children = cat?.children?.map(ch=>ch.name) || [parentName];
    subSel.innerHTML = children.map(ch=>`<option value="${ch}">${ch}</option>`).join('');
    if(selectedSub) subSel.value = selectedSub;
}

function renderImgPreview(){
    document.getElementById('pm-images').innerHTML = pmImages.map((f,i)=>`
        <div class="img-preview">
            <img src="uploads/products/${f}" onerror="this.src=''">
            <button class="img-remove" onclick="removeImg(${i})">&#x2715;</button>
        </div>`).join('');
}
function removeImg(i){ pmImages.splice(i,1); renderImgPreview(); }

async function handleImgUpload(input){
    for(const file of input.files){
        if(pmImages.length>=10) break;
        const fd=new FormData(); fd.append('image',file);
        const res=await fetch('admin.php?ajax=1&action=upload_image',{method:'POST',body:fd});
        const data=await res.json();
        if(data.ok) pmImages.push(data.file);
    }
    renderImgPreview(); input.value='';
}

async function saveProduct(){
    const id=document.getElementById('pm-id').value;
    const name = document.getElementById('pm-name').value;
    const desc = document.getElementById('pm-desc').value;
    const body={
        id, name, desc,
        brand:document.getElementById('pm-brand').value,
        price:parseFloat(document.getElementById('pm-price').value)||0,
        stock:parseInt(document.getElementById('pm-stock').value)||0,
        category_parent: document.getElementById('pm-category-parent').value,
        category_sub: document.getElementById('pm-category-sub').value,
        oem:document.getElementById('pm-oem').value,
        compat:document.getElementById('pm-compat').value,
        active:parseInt(document.getElementById('pm-active').value),
        images:pmImages,
        i18n: { lt:{name,description:desc}, lv:{name,description:desc}, et:{name,description:desc}, fi:{name,description:desc}, en:{name,description:desc}, ru:{name,description:desc} }
    };
    if(!body.name){alert('Įveskite pavadinimą!');return;}
    await fetch('admin.php?ajax=1&action=save_product',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    toast('Prekė išsaugota!'); closeModal('product-modal');
    setTimeout(()=>location.reload(),800);
}

async function deleteProduct(id){
    if(!confirm('Ar tikrai ištrinti šią prekę?')) return;
    await fetch('admin.php?ajax=1&action=delete_product',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    document.querySelector(`#products-table tr[data-id="${id}"]`)?.remove();
    toast('Prekė ištrinta');
}

// ── IMAGE HEALTH CHECK ────────────────────────────────────
async function checkAllImages(){
    const btn = document.getElementById('check-images-btn');
    const resultsEl = document.getElementById('image-check-results');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Tikrinama...';
    resultsEl.innerHTML = '<div style="padding:14px 0;color:#64748b;font-size:13px"><i class="fa-solid fa-spinner fa-spin"></i> Tikrinamos nuotraukos — gali užtrukti kelias minutes, jei prekių daug...</div>';

    try{
        const res = await fetch('admin.php?ajax=1&action=check_images');
        const data = await res.json();
        if(!data.ok){
            resultsEl.innerHTML = '<div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px">Klaida tikrinant nuotraukas.</div>';
        } else {
            renderImageCheckResults(data.results);
        }
    }catch(e){
        resultsEl.innerHTML = '<div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px">Serverio klaida.</div>';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-image"></i> Tikrinti nuotraukas';
}

function renderImageCheckResults(results){
    const resultsEl = document.getElementById('image-check-results');
    const problems = results.filter(r => r.status !== 'ok');

    if(!problems.length){
        resultsEl.innerHTML = '<div style="background:#dcfce7;color:#166534;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px"><i class="fa-solid fa-circle-check"></i> Visos prekės turi tinkamas nuotraukas!</div>';
        return;
    }

    const statusLabels = {
        no_images: {label:'Be nuotraukų', bg:'#fee2e2', fg:'#991b1b'},
        all_broken: {label:'Visos nuotraukos negyvos', bg:'#fee2e2', fg:'#991b1b'},
        partial_broken: {label:'Dalis nuotraukų negyva', bg:'#fef3c7', fg:'#92400e'},
    };

    resultsEl.innerHTML = `
        <div style="background:#fef3c7;color:#92400e;padding:10px 16px;border-radius:8px;font-size:13px;margin-bottom:10px;font-weight:600">
            <i class="fa-solid fa-triangle-exclamation"></i> Rasta ${problems.length} prekių su nuotraukų problemomis
        </div>
        <div style="margin-bottom:16px">
            ${problems.map(p=>{
                const s = statusLabels[p.status];
                return `<div style="display:flex;align-items:center;justify-content:space-between;background:${s.bg};color:${s.fg};padding:9px 14px;border-radius:8px;margin-bottom:6px;font-size:13px">
                    <div><strong>${p.name}</strong> — ${s.label} (${p.broken}/${p.total} negyvų)</div>
                    <button class="btn btn-red btn-sm" onclick="deleteProductFromCheck('${p.id}')"><i class="fa-solid fa-trash"></i> Ištrinti</button>
                </div>`;
            }).join('')}
        </div>`;
}

async function deleteProductFromCheck(id){
    if(!confirm('Ištrinti šią prekę dėl negyvų nuotraukų?')) return;
    await fetch('admin.php?ajax=1&action=delete_product',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    document.querySelector(`#products-table tr[data-id="${id}"]`)?.remove();
    toast('Prekė ištrinta');
    checkAllImages(); // perskaičiuoja sąrašą po ištrynimo
}

// ── SETTINGS ─────────────────────────────────────────────
let categoryMarkupData = <?= json_encode($settings['category_markup'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
let priceRangeMarkupData = <?= json_encode($settings['price_range_markup'] ?? [], JSON_UNESCAPED_UNICODE) ?>;

function toggleGmailPassVisibility(){
    const input = document.getElementById('s-gmail-pass');
    const btn = document.getElementById('gmail-pass-toggle-btn');
    if(input.type === 'password'){
        input.type = 'text';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
    }
}

async function testGmailConnection(){
    const btn = document.getElementById('test-gmail-btn');
    const resultEl = document.getElementById('test-gmail-result');
    const gmailUser = document.getElementById('s-gmail-user').value.trim();
    const gmailPass = document.getElementById('s-gmail-pass').value.trim();

    if(!gmailUser || !gmailPass){
        resultEl.innerHTML = '<div style="background:#fef3c7;color:#92400e;padding:10px 14px;border-radius:8px;font-size:13px">Įveskite Gmail adresą ir App Password prieš testuojant.</div>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testuojama...';
    resultEl.innerHTML = '';

    try{
        const res = await fetch('admin.php?ajax=1&action=test_gmail_connection', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({gmail_user: gmailUser, gmail_pass: gmailPass})
        });
        const data = await res.json();
        if(data.ok){
            resultEl.innerHTML = `<div style="background:#dcfce7;color:#166534;padding:10px 14px;border-radius:8px;font-size:13px"><i class="fa-solid fa-circle-check"></i> Prisijungimas sėkmingas! Testinis laiškas išsiųstas į ${gmailUser}.</div>`;
        } else {
            resultEl.innerHTML = `<div style="background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:13px"><i class="fa-solid fa-circle-xmark"></i> Klaida: ${data.msg}</div>`;
        }
    }catch(e){
        resultEl.innerHTML = '<div style="background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;font-size:13px">Serverio klaida testuojant.</div>';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Testuoti prisijungimą';
}

function switchHeroLangTab(code){
    document.querySelectorAll('.hero-lang-tab-btn').forEach(b=>{
        const active = b.dataset.lang === code;
        b.classList.toggle('active', active);
        b.style.borderBottomColor = active ? 'var(--accent)' : 'transparent';
        b.style.color = active ? 'var(--accent)' : '#64748b';
    });
    document.querySelectorAll('.hero-lang-panel').forEach(p=>{
        p.style.display = p.dataset.lang === code ? 'block' : 'none';
    });
}

async function saveSettings(){
    const heroEyebrow = {};
    const heroTitle = {};
    document.querySelectorAll('.hero-eyebrow-input').forEach(el => { heroEyebrow[el.dataset.lang] = el.value; });
    document.querySelectorAll('.hero-title-input').forEach(el => { heroTitle[el.dataset.lang] = el.value; });

    const body={
        gmail_user:document.getElementById('s-gmail-user').value,
        gmail_pass:document.getElementById('s-gmail-pass').value,
        admin_email:document.getElementById('s-admin-email').value,
        site_url:document.getElementById('s-site-url').value,
        site_name:document.getElementById('s-site-name').value,
        hero_eyebrow: heroEyebrow,
        hero_title: heroTitle,
        default_markup_percent: parseFloat(document.getElementById('s-default-markup').value) || 0,
        default_discount_percent: parseFloat(document.getElementById('s-default-discount').value) || 0,
        category_markup: categoryMarkupData,
        price_range_markup: priceRangeMarkupData,
    };
    await fetch('admin.php?ajax=1&action=save_settings',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    toast('Nustatymai išsaugoti!');
}

function renderPriceRangeMarkupList(){
    const el = document.getElementById('price-range-markup-list');
    if(!priceRangeMarkupData.length){
        el.innerHTML = '<p style="font-size:12.5px;color:#94a3b8">Dar nepridėta kainos diapazono antkainių.</p>';
        return;
    }
    el.innerHTML = priceRangeMarkupData.map((r,i)=>`
        <div style="display:flex;align-items:center;gap:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px">
            <span style="flex:1;font-size:13px;font-weight:600">${r.min.toFixed(2)} € – ${r.max.toFixed(2)} €</span>
            <span style="font-weight:700;color:var(--accent);font-size:13px">${r.markup}%</span>
            <button onclick="removePriceRangeMarkup(${i})" style="background:none;border:none;color:#dc2626;cursor:pointer"><i class="fa-solid fa-xmark"></i></button>
        </div>`).join('');
}

function addPriceRangeMarkup(){
    const min = parseFloat(document.getElementById('new-range-min').value);
    const max = parseFloat(document.getElementById('new-range-max').value);
    const pct = parseFloat(document.getElementById('new-range-percent').value);
    if(isNaN(min) || isNaN(max) || isNaN(pct)){ alert('Užpildykite visus laukus (nuo, iki, %)!'); return; }
    if(min >= max){ alert('"Nuo" turi būti mažesnis už "Iki"!'); return; }
    priceRangeMarkupData.push({min, max, markup: pct});
    priceRangeMarkupData.sort((a,b)=>a.min-b.min);
    document.getElementById('new-range-min').value = '';
    document.getElementById('new-range-max').value = '';
    document.getElementById('new-range-percent').value = '';
    renderPriceRangeMarkupList();
}

function removePriceRangeMarkup(idx){
    priceRangeMarkupData.splice(idx,1);
    renderPriceRangeMarkupList();
}

async function loadCategoryListForMarkup(){
    const res = await fetch('get_categories.php');
    const data = await res.json();
    if(!data.ok) return;
    const sel = document.getElementById('new-markup-category');
    sel.innerHTML = data.categories.map(c=>`<option value="${c.name}">${c.name}</option>`).join('');
    renderCategoryMarkupList();
    renderPriceRangeMarkupList();
}

function renderCategoryMarkupList(){
    const el = document.getElementById('category-markup-list');
    const entries = Object.entries(categoryMarkupData);
    if(!entries.length){
        el.innerHTML = '<p style="font-size:12.5px;color:#94a3b8">Dar nepridėta išskirtinių antkainių pagal kategoriją.</p>';
        return;
    }
    el.innerHTML = entries.map(([cat,pct])=>`
        <div style="display:flex;align-items:center;gap:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px">
            <span style="flex:1;font-size:13px;font-weight:600">${cat}</span>
            <span style="font-weight:700;color:var(--accent);font-size:13px">${pct}%</span>
            <button onclick="removeCategoryMarkup('${cat.replace(/'/g,"\\'")}')" style="background:none;border:none;color:#dc2626;cursor:pointer"><i class="fa-solid fa-xmark"></i></button>
        </div>`).join('');
}

function addCategoryMarkup(){
    const cat = document.getElementById('new-markup-category').value;
    const pct = parseFloat(document.getElementById('new-markup-percent').value);
    if(!cat || isNaN(pct)){ alert('Pasirinkite kategoriją ir įveskite procentą!'); return; }
    categoryMarkupData[cat] = pct;
    document.getElementById('new-markup-percent').value = '';
    renderCategoryMarkupList();
}

function removeCategoryMarkup(cat){
    delete categoryMarkupData[cat];
    renderCategoryMarkupList();
}

loadCategoryListForMarkup();

// ── EXPORT XLSX ───────────────────────────────────────────
function toggleExportPanel(){
    const panel = document.getElementById('export-panel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function exportXlsx(type){
    const from = document.getElementById('export-date-from').value;
    const to = document.getElementById('export-date-to').value;
    const params = new URLSearchParams({type});
    if(from) params.set('from', from);
    if(to) params.set('to', to);
    window.location.href = 'export_orders_xlsx.php?' + params.toString();
}

function exportInvoicesZip(type){
    const from = document.getElementById('export-date-from').value;
    const to = document.getElementById('export-date-to').value;
    const params = new URLSearchParams({type});
    if(from) params.set('from', from);
    if(to) params.set('to', to);
    window.location.href = 'export_invoices_zip.php?' + params.toString();
}

// ── TOAST ─────────────────────────────────────────────────
function toast(msg){
    const t=document.getElementById('toast'); t.textContent=msg; t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'),3000);
}

// Responsive sidebar toggle
if(window.innerWidth<768) document.getElementById('sidebar-toggle').style.display='block';
window.addEventListener('resize',()=>{ document.getElementById('sidebar-toggle').style.display=window.innerWidth<768?'block':'none'; });
</script>
<?php endif; ?>
</body>
</html>
