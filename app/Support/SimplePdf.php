<?php

declare(strict_types=1);

namespace IasdCentral\Support;

final class SimplePdf
{
    public static function create(string $title, array $rows): string
    {
        $pages = array_chunk($rows, 30);
        $objects = [];
        $pageIds = [];
        $fontId = 3;
        $nextId = 4;
        foreach ($pages as $pageRows) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageIds[] = $pageId;
            $stream = self::pageStream($title, $pageRows);
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 {$fontId} 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn (int $id): string => "{$id} 0 R", $pageIds)) . '] /Count ' . count($pageIds) . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= max(array_keys($objects)); $id++) $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0) . "\n";
        $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }

    private static function pageStream(string $title, array $rows): string
    {
        $commands = ['BT', '/F1 16 Tf', '40 560 Td', '(' . self::escape($title) . ') Tj', '/F1 8 Tf'];
        $y = 540;
        foreach ($rows as $row) {
            $line = implode('  |  ', array_map(static fn ($value): string => (string) $value, $row));
            $line = mb_strimwidth($line, 0, 170, '...');
            $commands[] = "1 0 0 1 40 {$y} Tm (" . self::escape($line) . ') Tj';
            $y -= 16;
        }
        $commands[] = 'ET';
        return implode("\n", $commands);
    }

    private static function escape(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }
}
