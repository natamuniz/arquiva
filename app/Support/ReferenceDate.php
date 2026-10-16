<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Converte datas vindas da API para um instante em UTC.
 * Data sem hora ("2025-01-10") vale 00:00 no fuso do projeto (America/Sao_Paulo).
 */
final class ReferenceDate
{
    public static function parse(string $value, string $field): CarbonImmutable
    {
        /** @var string $timezone */
        $timezone = config('arquiva.timezone');

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);
            } else {
                $date = CarbonImmutable::parse($value, $timezone);
            }
        } catch (Throwable) {
            $date = null;
        }

        if (! $date instanceof CarbonImmutable) {
            throw ValidationException::withMessages([
                $field => ['Use uma data (AAAA-MM-DD) ou data-hora ISO 8601.'],
            ]);
        }

        return $date->utc();
    }
}
