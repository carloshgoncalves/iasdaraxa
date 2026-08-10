<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || empty($argv[1]) || !is_file($argv[1])) {
    fwrite(STDERR, "Uso: php bin/inspect-xlsx.php <arquivo.xlsx>\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($argv[1]) !== true) exit(1);
$strings = [];
$shared = simplexml_load_string((string) $zip->getFromName('xl/sharedStrings.xml'));
foreach ($shared->si ?? [] as $item) {
    $parts = [];
    if (isset($item->t)) $parts[] = (string) $item->t;
    foreach ($item->r ?? [] as $run) $parts[] = (string) $run->t;
    $strings[] = implode('', $parts);
}
$workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
$workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
$sheets = $workbook->xpath('//x:sheet') ?: [];
foreach ($sheets as $index => $sheet) {
    echo "\n### " . (string) $sheet['name'] . "\n";
    $xml = simplexml_load_string((string) $zip->getFromName('xl/worksheets/sheet' . ($index + 1) . '.xml'));
    $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    foreach ($xml->xpath('//x:sheetData/x:row') ?: [] as $row) {
        $values = [];
        foreach ($row->c as $cell) {
            $reference = (string) $cell['r'];
            $value = (string) $cell->v;
            if ((string) $cell['t'] === 's') $value = $strings[(int) $value] ?? '';
            if (isset($cell->f)) $value .= ' {=' . (string) $cell->f . '}';
            if (trim($value) !== '') $values[] = $reference . '=' . preg_replace('/\s+/', ' ', trim($value));
        }
        if ($values !== []) echo implode("\t", $values) . "\n";
    }
}
$zip->close();
