<?php

declare(strict_types=1);

namespace IasdCentral\Support;

use DOMDocument;
use DOMXPath;
use ZipArchive;

final class Spreadsheet
{
    public static function createXlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'iasd-xlsx-');
        if ($path === false) {
            throw new \RuntimeException('Nao foi possivel criar a planilha.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Nao foi possivel montar a planilha.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Planejamento" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF7A1F38"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');

        $sheetRows = [];
        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach (array_values($row) as $columnIndex => $value) {
                $reference = self::columnName($columnIndex + 1) . ($rowIndex + 1);
                $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $cells[] = '<c r="' . $reference . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $text . '</t></is></c>';
            }
            $sheetRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="12" customWidth="1"/><col min="2" max="2" width="42" customWidth="1"/><col min="3" max="13" width="18" customWidth="1"/></cols><sheetData>' . implode('', $sheetRows) . '</sheetData><autoFilter ref="A1:' . self::columnName(count($rows[0] ?? [])) . '1"/></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        return $path;
    }

    public static function read(string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') return self::readXlsx($path);
        if ($extension === 'xls') return self::readLegacyXls($path);
        return self::readCsv($path);
    }

    private static function readLegacyXls(string $path): array
    {
        $directory = sys_get_temp_dir() . '/iasd-xls-' . bin2hex(random_bytes(6));
        if (!mkdir($directory, 0700) || !copy($path, $directory . '/importacao.xls')) {
            throw new \RuntimeException('Nao foi possivel preparar o arquivo XLS.');
        }
        $profile = $directory . '/profile';
        $command = 'libreoffice -env:UserInstallation=' . escapeshellarg('file://' . $profile)
            . ' --headless --convert-to xlsx --outdir ' . escapeshellarg($directory)
            . ' ' . escapeshellarg($directory . '/importacao.xls') . ' 2>&1';
        exec($command, $output, $status);
        $converted = $directory . '/importacao.xlsx';
        try {
            if ($status !== 0 || !is_file($converted)) throw new \InvalidArgumentException('O arquivo XLS nao pode ser convertido.');
            return self::readXlsx($converted);
        } finally {
            self::removeDirectory($directory);
        }
    }

    private static function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException('Arquivo XLSX invalido.');
        }
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $document = new DOMDocument();
            $document->loadXML($sharedXml);
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($xpath->query('//x:si') as $node) {
                $parts = [];
                foreach ($xpath->query('.//x:t', $node) as $text) $parts[] = $text->textContent;
                $shared[] = implode('', $parts);
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) throw new \InvalidArgumentException('A primeira planilha nao foi encontrada.');
        $document = new DOMDocument();
        $document->loadXML($sheetXml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($xpath->query('//x:sheetData/x:row') as $rowNode) {
            $row = [];
            foreach ($xpath->query('./x:c', $rowNode) as $cell) {
                $reference = $cell->attributes?->getNamedItem('r')?->nodeValue ?? 'A1';
                $index = self::columnIndex(preg_replace('/\d+/', '', $reference)) - 1;
                $type = $cell->attributes?->getNamedItem('t')?->nodeValue ?? '';
                $valueNode = $xpath->query($type === 'inlineStr' ? './/x:t' : './x:v', $cell)->item(0);
                $value = $valueNode?->textContent ?? '';
                if ($type === 's') $value = $shared[(int) $value] ?? '';
                $row[$index] = trim($value);
            }
            if ($row !== []) {
                ksort($row);
                $max = max(array_keys($row));
                $rows[] = array_map(static fn (int $index): string => $row[$index] ?? '', range(0, $max));
            }
        }
        return $rows;
    }

    private static function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new \InvalidArgumentException('Nao foi possivel ler o CSV.');
        $sample = (string) fgets($handle);
        rewind($handle);
        $delimiter = substr_count($sample, ';') >= substr_count($sample, ',') ? ';' : ',';
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) $rows[] = array_map('trim', $row);
        fclose($handle);
        return $rows;
    }

    private static function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }
        return $name;
    }

    private static function columnIndex(string $name): int
    {
        $index = 0;
        foreach (str_split(strtoupper($name)) as $character) $index = $index * 26 + ord($character) - 64;
        return $index;
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        rmdir($directory);
    }
}
