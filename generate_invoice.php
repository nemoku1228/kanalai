<?php
/**
 * generate_invoice.php
 * Generuoja PVM sąskaitą-faktūrą PDF formatu pagal pateiktą šabloną
 * (Pro Baltic, MB rekvizitai), naudojant TCPDF biblioteką.
 *
 * NAUDOJIMAS (iš kito PHP failo):
 *   require __DIR__ . '/generate_invoice.php';
 *   $pdfPath = generateInvoicePdf($order, $invoiceData, $settings);
 *   // $pdfPath - absoliutus kelias į sukurtą PDF failą, kurį gali prisegti į laišką
 *
 * PRIEŠ NAUDOJIMĄ:
 *   Įsitikink, kad TCPDF biblioteka įkelta į /TCPDF/ aplanką serverio šaknyje
 *   (atsisiunčiama iš https://github.com/tecnickcom/TCPDF/releases)
 */

function getNextInvoiceNumber() {
    $counterFile = __DIR__ . '/invoice_counter.json';
    $data = file_exists($counterFile) ? json_decode(file_get_contents($counterFile), true) : ['last_number' => 0];
    $next = ($data['last_number'] ?? 0) + 1;
    file_put_contents($counterFile, json_encode(['last_number' => $next]), LOCK_EX);
    return $next;
}

/**
 * Konvertuoja sumą į žodžius lietuviškai (eurų ir centų).
 * Paprastas, bet veikiantis sprendimas tipiškoms sumoms (0-999999).
 */
function numberToWordsLT($amount) {
    $euros = floor($amount);
    $cents = round(($amount - $euros) * 100);

    $ones = ['', 'vienas', 'du', 'trys', 'keturi', 'penki', 'šeši', 'septyni', 'aštuoni', 'devyni'];
    $teens = ['dešimt', 'vienuolika', 'dvylika', 'trylika', 'keturiolika', 'penkiolika', 'šešiolika', 'septyniolika', 'aštuoniolika', 'devyniolika'];
    $tens = ['', '', 'dvidešimt', 'trisdešimt', 'keturiasdešimt', 'penkiasdešimt', 'šešiasdešimt', 'septyniasdešimt', 'aštuoniasdešimt', 'devyniasdešimt'];

    $convertHundreds = function($n) use ($ones, $teens, $tens) {
        $result = '';
        if ($n >= 100) {
            $h = (int)($n / 100);
            $result .= ($h == 1 ? 'šimtas' : $ones[$h] . ' šimtai') . ' ';
            $n %= 100;
        }
        if ($n >= 10 && $n < 20) {
            $result .= $teens[$n - 10] . ' ';
        } elseif ($n >= 20) {
            $result .= $tens[(int)($n / 10)] . ' ';
            $n %= 10;
            if ($n > 0) $result .= $ones[$n] . ' ';
        } elseif ($n > 0) {
            $result .= $ones[$n] . ' ';
        }
        return trim($result);
    };

    $euroText = $euros == 0 ? 'nulis' : $convertHundreds((int)$euros);
    return trim($euroText) . ' Eur ' . sprintf('%02d', $cents) . ' ct';
}

/**
 * Sugeneruoja PVM sąskaitos-faktūros PDF.
 *
 * @param array $order Užsakymo duomenys (id, cart, total, customer, date)
 * @param array $invoiceData Įmonės duomenys (company_name, company_code, vat_code, company_address)
 * @param array $settings Bendri svetainės nustatymai (site_name ir t.t.)
 * @return string|null Absoliutus kelias į sukurtą PDF failą, arba null jei nepavyko
 */
function generateInvoicePdf($order, $invoiceData, $settings = []) {
    $tcpdfPath = __DIR__ . '/TCPDF/tcpdf.php';
    if (!file_exists($tcpdfPath)) {
        @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s')."] TCPDF nerastas: {$tcpdfPath}\n", FILE_APPEND);
        return null;
    }
    require_once $tcpdfPath;

    // ── PARDAVĖJO REKVIZITAI (pakeisk pagal savo įmonės duomenis) ──────
    $sellerName    = 'Pro Baltic, MB';
    $sellerAddress = 'Perkūnkiemio g. 13, LT-12119 Vilnius';
    $sellerCode    = '306055227';
    $sellerVat     = 'LT100015047819';
    $sellerEmail   = 'pagalba.probaltic@gmail.com';
    $sellerPhone   = '+37060210336';

    $invoiceNumber = getNextInvoiceNumber();
    $invoiceSeries = 'MP-LT-INV';
    $fullInvoiceNo = $invoiceSeries . ' Nr. ' . $invoiceNumber;

    // ── SKAIČIAVIMAI ────────────────────────────────────────────────
    $totalWithVat = (float)str_replace([' ', '€', ','], ['', '', '.'], $order['total']);
    $vatRate = 21;

    // Pristatymo kaina — apskaičiuojama atskirai, kad sąskaitos prekių
    // lentelė + pristatymo eilutė TIKSLIAI sutaptų su order['total']
    $itemsSubtotalWithVat = 0;
    foreach ($order['cart'] as $item) {
        $itemsSubtotalWithVat += (float)$item['price'] * (int)$item['quantity'];
    }
    $itemsSubtotalWithVat = round($itemsSubtotalWithVat, 2);
    $shippingCostWithVat = round($totalWithVat - $itemsSubtotalWithVat, 2);
    if ($shippingCostWithVat < 0) $shippingCostWithVat = 0; // apsauga nuo apvalinimo paklaidų

    $delLabelsMap = ['courier' => 'Pristatymas kurjeriu', 'post' => 'Pristatymas paštomatu', 'bus' => 'Pristatymas autobusų siunta'];
    $shippingLabel = $delLabelsMap[$order['delivery']] ?? 'Pristatymas';

    $totalWithoutVat = round($totalWithVat / (1 + $vatRate / 100), 2);
    $vatAmount = round($totalWithVat - $totalWithoutVat, 2);

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('market');
    $pdf->SetAuthor($sellerName);
    $pdf->SetTitle('PVM saskaita faktura ' . $fullInvoiceNo);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', '', 9);

    // ── ANTRAŠTĖ ────────────────────────────────────────────────────
    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->Cell(0, 8, 'PVM SĄSKAITA-FAKTŪRA', 0, 1, 'C');
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 6, 'Serija ' . $invoiceSeries . ' Nr. ' . $invoiceNumber, 0, 1, 'C');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 6, date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(4);

    // ── PARDAVĖJAS / PIRKĖJAS (dvi kolonos) ──────────────────────────
    $sellerHtml = "<b>Pardavėjas:</b> {$sellerName}<br>
Adresas: {$sellerAddress}<br>
Įmonės kodas: {$sellerCode}<br>
PVM mok. kodas: {$sellerVat}<br>
El. paštas: {$sellerEmail}<br>
Tel.: {$sellerPhone}";

    $buyerName = $invoiceData['company_name'];
    $buyerAddress = $invoiceData['company_address'];
    $buyerCode = $invoiceData['company_code'];
    $buyerVat = $invoiceData['vat_code'] ?? '';

    $buyerHtml = "<b>Pirkėjas:</b> {$buyerName}<br>
Adresas: {$buyerAddress}<br>
Įmonės kodas: {$buyerCode}<br>" .
        (!empty($buyerVat) ? "PVM mok. kodas: {$buyerVat}<br>" : '') .
        "Užsakymo numeris: {$order['id']}";

    $pdf->writeHTMLCell(90, 0, 15, $pdf->GetY(), $sellerHtml, 0, 0, false, true, 'L');
    $pdf->writeHTMLCell(90, 0, 105, $pdf->GetY(), $buyerHtml, 0, 1, false, true, 'L');
    $pdf->Ln(8);

    // ── PREKIŲ LENTELĖ ────────────────────────────────────────────────
    $pdf->SetFont('dejavusans', 'B', 8.5);
    $pdf->SetFillColor(241, 245, 249);
    $colWidths = [10, 70, 18, 14, 22, 22, 24];
    $headers = ['Nr.', 'Pavadinimas', 'Kiekis', 'Matas', 'Kaina', 'Suma', 'Suma su PVM'];
    foreach ($headers as $i => $h) {
        $pdf->Cell($colWidths[$i], 7, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();

    $pdf->SetFont('dejavusans', '', 8.5);
    $itemNo = 1;
    foreach ($order['cart'] as $item) {
        $itemPriceWithVat = (float)$item['price'];
        $itemPriceNoVat = round($itemPriceWithVat / (1 + $vatRate / 100), 2);
        $itemSumNoVat = round($itemPriceNoVat * $item['quantity'], 2);
        $itemSumWithVat = round($itemPriceWithVat * $item['quantity'], 2);

        $nameText = $item['name'] . (!empty($item['sku']) ? "\nSKU: {$item['sku']}" : '');

        $startY = $pdf->GetY();
        // Apskaičiuojam reikiamą aukštį BE faktinio piešimo (naudojant getStringHeight),
        // kad MultiCell vėliau nepaliktų kursoriaus netikėtoje vietoje
        $rowHeight = $pdf->getStringHeight($colWidths[1], $nameText, false, true, '', 1);
        if ($rowHeight < 7) $rowHeight = 7;

        $pdf->SetXY(15, $startY);
        $pdf->Cell($colWidths[0], $rowHeight, $itemNo, 1, 0, 'C');
        $pdf->SetXY(15 + $colWidths[0], $startY);
        $pdf->MultiCell($colWidths[1], $rowHeight, $nameText, 1, 'L', false, 0, 15 + $colWidths[0], $startY, true);
        $pdf->SetXY(15 + $colWidths[0] + $colWidths[1], $startY);
        $pdf->Cell($colWidths[2], $rowHeight, $item['quantity'], 1, 0, 'C');
        $pdf->Cell($colWidths[3], $rowHeight, 'vnt.', 1, 0, 'C');
        $pdf->Cell($colWidths[4], $rowHeight, number_format($itemPriceNoVat, 2) . ' Eur', 1, 0, 'R');
        $pdf->Cell($colWidths[5], $rowHeight, number_format($itemSumNoVat, 2) . ' Eur', 1, 0, 'R');
        $pdf->Cell($colWidths[6], $rowHeight, number_format($itemSumWithVat, 2) . ' Eur', 1, 0, 'R');
        // Aiškiai nustatom Y poziciją kitai eilutei — NE per Cell($ln=1) automatiką
        $pdf->SetXY(15, $startY + $rowHeight);
        $itemNo++;
    }

    // ── PRISTATYMO EILUTĖ (jei taikoma) ──────────────────────────────
    if ($shippingCostWithVat > 0) {
        $shippingNoVat = round($shippingCostWithVat / (1 + $vatRate / 100), 2);
        $startY = $pdf->GetY();
        $rowHeight = $pdf->getStringHeight($colWidths[1], $shippingLabel, false, true, '', 1);
        if ($rowHeight < 7) $rowHeight = 7;

        $pdf->SetXY(15, $startY);
        $pdf->Cell($colWidths[0], $rowHeight, $itemNo, 1, 0, 'C');
        $pdf->SetXY(15 + $colWidths[0], $startY);
        $pdf->MultiCell($colWidths[1], $rowHeight, $shippingLabel, 1, 'L', false, 0, 15 + $colWidths[0], $startY, true);
        $pdf->SetXY(15 + $colWidths[0] + $colWidths[1], $startY);
        $pdf->Cell($colWidths[2], $rowHeight, 1, 1, 0, 'C');
        $pdf->Cell($colWidths[3], $rowHeight, 'vnt.', 1, 0, 'C');
        $pdf->Cell($colWidths[4], $rowHeight, number_format($shippingNoVat, 2) . ' Eur', 1, 0, 'R');
        $pdf->Cell($colWidths[5], $rowHeight, number_format($shippingNoVat, 2) . ' Eur', 1, 0, 'R');
        $pdf->Cell($colWidths[6], $rowHeight, number_format($shippingCostWithVat, 2) . ' Eur', 1, 0, 'R');
        $pdf->SetXY(15, $startY + $rowHeight);
        $itemNo++;
    }

    $totalQty = array_sum(array_column($order['cart'], 'quantity'));
    $pdf->SetFont('dejavusans', 'B', 8.5);
    $pdf->Cell(array_sum($colWidths) - $colWidths[6], 7, 'Viso prekių: ' . $totalQty, 1, 0, 'R');
    $pdf->Cell($colWidths[6], 7, '', 1, 1);
    $pdf->Ln(6);

    // ── SUVESTINĖ ──────────────────────────────────────────────────
    $pdf->SetFont('dejavusans', '', 9);
    $summaryX = 130;
    $pdf->SetXY($summaryX, $pdf->GetY());
    $pdf->Cell(35, 6, 'Viso be PVM', 1, 0, 'L');
    $pdf->Cell(30, 6, number_format($totalWithoutVat, 2) . ' Eur', 1, 1, 'R');
    $pdf->SetXY($summaryX, $pdf->GetY());
    $pdf->Cell(35, 6, "PVM {$vatRate}%", 1, 0, 'L');
    $pdf->Cell(30, 6, number_format($vatAmount, 2) . ' Eur', 1, 1, 'R');
    $pdf->SetXY($summaryX, $pdf->GetY());
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(35, 6, 'Viso', 1, 0, 'L');
    $pdf->Cell(30, 6, number_format($totalWithVat, 2) . ' Eur', 1, 1, 'R');
    $pdf->Ln(4);

    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 6, 'Suma žodžiais: ' . numberToWordsLT($totalWithVat), 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(0, 6, 'Apmokėta suma: ' . number_format($totalWithVat, 2) . ' Eur', 0, 1, 'R');
    $pdf->Cell(0, 6, 'Suma apmokėjimui: 0.00 Eur', 0, 1, 'R');

    // ── SAUGOJIMAS ──────────────────────────────────────────────────
    $invoicesDir = __DIR__ . '/invoices';
    if (!is_dir($invoicesDir)) mkdir($invoicesDir, 0755, true);
    $filename = 'invoice_' . $order['id'] . '.pdf';
    $fullPath = $invoicesDir . '/' . $filename;
    $pdf->Output($fullPath, 'F');

    if (!file_exists($fullPath)) return null;
    return ['path' => $fullPath, 'invoice_number' => $fullInvoiceNo];
}

/**
 * Sugeneruoja KREDITINĘ sąskaitą-faktūrą (sąskaitos atšaukimo dokumentą),
 * kai užsakymas atšaukiamas po to, kai jau buvo išrašyta originali PVM sąskaita.
 *
 * @param array $order Užsakymo duomenys (turi turėti $order['invoice'])
 * @return string|null Absoliutus kelias į sukurtą PDF failą, arba null
 */
function generateCreditInvoicePdf($order) {
    $tcpdfPath = __DIR__ . '/TCPDF/tcpdf.php';
    if (!file_exists($tcpdfPath)) {
        @file_put_contents(__DIR__.'/mail_errors.log', '['.date('Y-m-d H:i:s')."] TCPDF nerastas kreditinei sąskaitai\n", FILE_APPEND);
        return null;
    }

    // PASTABA: anksčiau čia buvo tikrinama, ar originalios sąskaitos PDF
    // failas egzistuoja diske — bet jei TCPDF tuo metu buvo neprieinamas
    // (pvz. dar neįkeltas), originalo PDF galėjo nebūti sukurtas, nors
    // klientas TIKRAI pažymėjo norą gauti sąskaitą. Todėl kreditinę
    // generuojame visada, kai order['invoice'] duomenys egzistuoja —
    // tai patikimiau nei priklausyti nuo praeities PDF generavimo sėkmės.
    if (empty($order['invoice']) || empty($order['invoice']['company_name'])) {
        return null; // klientas nepageidavo PVM sąskaitos — nėra ką kreditinuoti
    }

    require_once $tcpdfPath;

    $sellerName    = 'Pro Baltic, MB';
    $sellerAddress = 'Perkūnkiemio g. 13, LT-12119 Vilnius';
    $sellerCode    = '306055227';
    $sellerVat     = 'LT100015047819';
    $sellerEmail   = 'pagalba.probaltic@gmail.com';
    $sellerPhone   = '+37060210336';

    $creditNumber = getNextCreditInvoiceNumber();
    $creditSeries = 'MP-LT-CREDIT';
    $originalSeries = 'MP-LT-INV';

    $invoiceData = $order['invoice'];
    $totalWithVat = (float)str_replace([' ', '€', ','], ['', '', '.'], $order['total']);
    $vatRate = 21;
    $totalWithoutVat = round($totalWithVat / (1 + $vatRate / 100), 2);
    $vatAmount = round($totalWithVat - $totalWithoutVat, 2);

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('market');
    $pdf->SetAuthor($sellerName);
    $pdf->SetTitle('Kreditine saskaita ' . $creditSeries . '-' . $order['id']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', '', 9);

    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->SetTextColor(180, 30, 30);
    $pdf->Cell(0, 8, 'KREDITINĖ PVM SĄSKAITA-FAKTŪRA', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 6, 'Serija ' . $creditSeries . ' Nr. ' . $creditNumber, 0, 1, 'C');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 6, date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('dejavusans', 'I', 9);
    $pdf->Cell(0, 6, 'Anuliuojama sąskaita: ' . $originalSeries . '-' . $order['id'] . ' (užsakymas atšauktas)', 0, 1, 'C');
    $pdf->Ln(4);

    $sellerHtml = "<b>Pardavėjas:</b> {$sellerName}<br>
Adresas: {$sellerAddress}<br>
Įmonės kodas: {$sellerCode}<br>
PVM mok. kodas: {$sellerVat}<br>
El. paštas: {$sellerEmail}<br>
Tel.: {$sellerPhone}";

    $buyerHtml = "<b>Pirkėjas:</b> {$invoiceData['company_name']}<br>
Adresas: {$invoiceData['company_address']}<br>
Įmonės kodas: {$invoiceData['company_code']}<br>" .
        (!empty($invoiceData['vat_code']) ? "PVM mok. kodas: {$invoiceData['vat_code']}<br>" : '') .
        "Užsakymo numeris: {$order['id']}";

    $pdf->writeHTMLCell(90, 0, 15, $pdf->GetY(), $sellerHtml, 0, 0, false, true, 'L');
    $pdf->writeHTMLCell(90, 0, 105, $pdf->GetY(), $buyerHtml, 0, 1, false, true, 'L');
    $pdf->Ln(8);

    // ── ANULIUOJAMŲ SUMŲ LENTELĖ (neigiamos reikšmės) ────────────────
    $pdf->SetFont('dejavusans', 'B', 8.5);
    $pdf->SetFillColor(254, 226, 226);
    $colWidths = [10, 90, 40, 40];
    $headers = ['Nr.', 'Aprašymas', 'Suma be PVM', 'Suma su PVM'];
    foreach ($headers as $i => $h) {
        $pdf->Cell($colWidths[$i], 7, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();

    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->Cell($colWidths[0], 7, '1', 1, 0, 'C');
    $pdf->Cell($colWidths[1], 7, 'Užsakymo Nr. ' . $order['id'] . ' anuliavimas', 1, 0, 'L');
    $pdf->Cell($colWidths[2], 7, '-' . number_format($totalWithoutVat, 2) . ' Eur', 1, 0, 'R');
    $pdf->Cell($colWidths[3], 7, '-' . number_format($totalWithVat, 2) . ' Eur', 1, 1, 'R');
    $pdf->Ln(6);

    $pdf->SetFont('dejavusans', '', 9);
    $summaryX = 130;
    $pdf->SetXY($summaryX, $pdf->GetY());
    $pdf->Cell(35, 6, 'Viso be PVM', 1, 0, 'L');
    $pdf->Cell(30, 6, '-' . number_format($totalWithoutVat, 2) . ' Eur', 1, 1, 'R');
    $pdf->SetXY($summaryX, $pdf->GetY());
    $pdf->Cell(35, 6, "PVM {$vatRate}%", 1, 0, 'L');
    $pdf->Cell(30, 6, '-' . number_format($vatAmount, 2) . ' Eur', 1, 1, 'R');
    $pdf->SetXY($summaryX, $pdf->GetY());
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(35, 6, 'Viso', 1, 0, 'L');
    $pdf->Cell(30, 6, '-' . number_format($totalWithVat, 2) . ' Eur', 1, 1, 'R');
    $pdf->Ln(6);

    $pdf->SetFont('dejavusans', 'I', 9);
    $pdf->Cell(0, 6, 'Šis dokumentas anuliuoja anksčiau išrašytą PVM sąskaitą-faktūrą Nr. ' . $originalSeries . '-' . $order['id'] . '.', 0, 1, 'L');

    $invoicesDir = __DIR__ . '/invoices';
    if (!is_dir($invoicesDir)) mkdir($invoicesDir, 0755, true);
    $filename = 'credit_invoice_' . $order['id'] . '.pdf';
    $fullPath = $invoicesDir . '/' . $filename;
    $pdf->Output($fullPath, 'F');

    if (!file_exists($fullPath)) return null;
    return ['path' => $fullPath, 'invoice_number' => $creditSeries . '-' . $creditNumber];
}

function getNextCreditInvoiceNumber() {
    $counterFile = __DIR__ . '/credit_invoice_counter.json';
    $data = file_exists($counterFile) ? json_decode(file_get_contents($counterFile), true) : ['last_number' => 0];
    $next = ($data['last_number'] ?? 0) + 1;
    file_put_contents($counterFile, json_encode(['last_number' => $next]), LOCK_EX);
    return $next;
}
