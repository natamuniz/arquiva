<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

final class PdfPageCounter
{
    /** @throws RuntimeException quando o PDF não pode ser lido */
    public static function count(string $absolutePath): int
    {
        try {
            $pages = count((new Parser)->parseFile($absolutePath)->getPages());
        } catch (Throwable $e) {
            throw new RuntimeException('PDF inválido ou corrompido: '.$e->getMessage(), previous: $e);
        }

        if ($pages < 1) {
            throw new RuntimeException('PDF sem páginas legíveis.');
        }

        return $pages;
    }
}
