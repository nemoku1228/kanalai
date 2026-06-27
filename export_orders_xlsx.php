<?php
/**
 * export_orders_xlsx.php
 * Eksportuoja užsakymus į TIKRĄ .xlsx failą (ne CSV), su galimybe filtruoti
 * pagal datos diapazoną. Generuojama gryna PHP (ZipArchive), be jokios
 * išorinės bibliotekos (PhpSpreadsheet ir pan.) — XLSX yra tiesiog ZIP
 * archyvas su XML failais viduje, kuriuos rašome rankiniu būdu.
 *
 * GET parametrai:
 *   ?from=2026-01-01   (pasirinktinai)
 *   ?to=2026-12-31     (pasirinktinai)
 *   ?type=orders        (orders | invoices)
 */

session_start();
if (!isset($_SESSION['admin'])) { http_response_code(403); echo 'Neturite teisės.'; exit; }

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$type = $_GET['type'] ?? 'orders';

$orders = file_exists(__DIR__.'/orders.json') ? json_decode(file_get_contents(__DIR__.'/orders.json'), true) : [];

// ── Datos diapazono filtravimas ──────────────────────────────────
function withinRange($dateStr, $from, $to) {
    $date = substr($dateStr, 0, 10); // YYYY-MM-DD dalis
    if ($from && $date < $from) return false;
    if ($to && $date > $to) return false;
    return true;
}

$filtered = array_values(array_filter($orders, fn($o) => withinRange($o['date'] ?? '', $from, $to)));

// ── XLSX rinkmenos turinio sudėjimas (eilutės ir stulpeliai) ─────
if ($type === 'invoices') {
    // Tik užsakymai, kurie TURĖJO PVM sąskaitą
    $filtered = array_values(array_filter($filtered, fn($o) => !empty($o['invoice'])));
    $headers = ['Užsakymo Nr.', 'Sąskaitos Nr.', 'Data', 'Įmonė', 'Įmonės kodas', 'PVM kodas', 'Adresas', 'Suma be PVM', 'PVM 21%', 'Suma su PVM', 'Statusas', 'Kreditinės Nr.', 'Sąskaitos failas'];
    $rows = [];
    foreach ($filtered as $o) {
        $inv = $o['invoice'];
        $totalWithVat = (float)str_replace([' ', '€', ','], ['', '', '.'], $o['total'] ?? '0');
        $totalNoVat = round($totalWithVat / 1.21, 2);
        $vatAmount = round($totalWithVat - $totalNoVat, 2);
        $rows[] = [
            $o['id'] ?? '',
            $o['invoice_number'] ?? '—',
            $o['date'] ?? '',
            $inv['company_name'] ?? '',
            $inv['company_code'] ?? '',
            $inv['vat_code'] ?? '',
            $inv['company_address'] ?? '',
            number_format($totalNoVat, 2) . ' €',
            number_format($vatAmount, 2) . ' €',
            $o['total'] ?? '',
            $o['status'] ?? '',
            $o['credit_invoice_number'] ?? '',
            'invoice_' . ($o['id'] ?? '') . '.pdf',
        ];
    }
    $filename = 'saskaitos_' . date('Y-m-d') . '.xlsx';
} else {
    $headers = ['Užsakymo Nr.', 'Data', 'Vardas', 'Pavardė', 'El. paštas', 'Telefonas', 'Miestas', 'Gatvė', 'Pašto kodas', 'Pristatymas', 'Sekimo Nr.', 'Suma be PVM', 'PVM 21%', 'Suma su PVM', 'Statusas', 'IP adresas', 'Sąskaitos Nr.'];
    $rows = [];
    foreach ($filtered as $o) {
        $c = $o['customer'] ?? [];
        $totalWithVat = (float)str_replace([' ', '€', ','], ['', '', '.'], $o['total'] ?? '0');
        $totalNoVat = round($totalWithVat / 1.21, 2);
        $vatAmount = round($totalWithVat - $totalNoVat, 2);
        $rows[] = [
            $o['id'] ?? '',
            $o['date'] ?? '',
            $c['name'] ?? '',
            $c['surname'] ?? '',
            $c['email'] ?? '',
            $c['phone'] ?? '',
            $c['city'] ?? '',
            $c['street'] ?? '',
            $c['zipcode'] ?? '',
            $o['delivery'] ?? '',
            $o['tracking'] ?? '',
            number_format($totalNoVat, 2) . ' €',
            number_format($vatAmount, 2) . ' €',
            $o['total'] ?? '',
            $o['status'] ?? '',
            $o['ip'] ?? '',
            $o['invoice_number'] ?? '',
        ];
    }
    $filename = 'uzsakymai_' . date('Y-m-d') . '.xlsx';
}

// ── XLSX generavimas (minimalus, bet validus formatas) ───────────
function xlsxColumnLetter($index) {
    $letter = '';
    while ($index >= 0) {
        $letter = chr($index % 26 + 65) . $letter;
        $index = intdiv($index, 26) - 1;
    }
    return $letter;
}

function xlsxEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function buildSheetXml($headers, $rows) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetData>';

    // Antraščių eilutė (paryškinta per stilių indeksą s="1")
    $xml .= '<row r="1">';
    foreach ($headers as $i => $h) {
        $col = xlsxColumnLetter($i);
        $xml .= '<c r="' . $col . '1" t="inlineStr" s="1"><is><t xml:space="preserve">' . xlsxEscape($h) . '</t></is></c>';
    }
    $xml .= '</row>';

    // Duomenų eilutės
    foreach ($rows as $rIdx => $row) {
        $r = $rIdx + 2;
        $xml .= '<row r="' . $r . '">';
        foreach ($row as $i => $val) {
            $col = xlsxColumnLetter($i);
            $xml .= '<c r="' . $col . $r . '" t="inlineStr"><is><t xml:space="preserve">' . xlsxEscape($val) . '</t></is></c>';
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData></worksheet>';
    return $xml;
}

$tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
$zip = new ZipArchive();
$zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

// [Content_Types].xml
$zip->addFromString('[Content_Types].xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
    '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
    '<Default Extension="xml" ContentType="application/xml"/>' .
    '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
    '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
    '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
    '</Types>'
);

// _rels/.rels
$zip->addFromString('_rels/.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
    '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
    '</Relationships>'
);

// xl/_rels/workbook.xml.rels
$zip->addFromString('xl/_rels/workbook.xml.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
    '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
    '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
    '</Relationships>'
);

// xl/workbook.xml
$sheetTitle = $type === 'invoices' ? 'Saskaitos' : 'Uzsakymai';
$zip->addFromString('xl/workbook.xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
    '<sheets><sheet name="' . $sheetTitle . '" sheetId="1" r:id="rId1"/></sheets>' .
    '</workbook>'
);

// xl/styles.xml — minimalus stilius su paryškintu antraščių formatu (s="1")
$zip->addFromString('xl/styles.xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
    '<fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><name val="Calibri"/></font></fonts>' .
    '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE7EAEE"/></patternFill></fill></fills>' .
    '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
    '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
    '<cellXfs count="2">' .
    '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
    '<xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' .
    '</cellXfs>' .
    '</styleSheet>'
);

// xl/worksheets/sheet1.xml — patys duomenys
$zip->addFromString('xl/worksheets/sheet1.xml', buildSheetXml($headers, $rows));

$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
unlink($tmpFile);
