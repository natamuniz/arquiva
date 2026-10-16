<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * Gera um PDF mínimo e válido (com tabela xref correta), para seeders e testes.
 * Dados 100% fictícios.
 */
final class SamplePdf
{
    public static function make(int $pages = 1, string $text = 'Documento de exemplo - Arquiva'): string
    {
        $pages = max(1, $pages);
        $objects = [];

        $kids = [];
        for ($i = 0; $i < $pages; $i++) {
            $kids[] = (4 + $i * 2).' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pages.' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        for ($i = 0; $i < $pages; $i++) {
            $pageId = 4 + $i * 2;
            $contentId = $pageId + 1;
            $line = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text.' - pagina '.($i + 1));
            $stream = "BT /F1 14 Tf 72 720 Td ({$line}) Tj ET";

            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
                .'/Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
