<?php

declare(strict_types=1);

use App\Enums\Secrecy;
use App\Models\Classification;

it('ordena o sigilo: publico < restrito < sigiloso', function (Secrecy $clearance, Secrecy $secrecy, bool $expected): void {
    expect($clearance->covers($secrecy))->toBe($expected);
})->with([
    [Secrecy::Publico, Secrecy::Publico, true],
    [Secrecy::Publico, Secrecy::Restrito, false],
    [Secrecy::Restrito, Secrecy::Publico, true],
    [Secrecy::Restrito, Secrecy::Sigiloso, false],
    [Secrecy::Sigiloso, Secrecy::Sigiloso, true],
]);

it('valida o formato do código de classe', function (string $code, ?string $parent, bool $expected): void {
    expect(Classification::isValidCode($code, $parent))->toBe($expected);
})->with([
    'raiz' => ['010', null, true],
    'raiz curta' => ['01', null, false],
    'raiz longa' => ['0100', null, false],
    'filha' => ['010.1', '010', true],
    'neta' => ['010.1.12', '010.1', true],
    'filha sem separador' => ['0101', '010', false],
    'filha de outro pai' => ['020.1', '010', false],
    'segmento longo' => ['010.1234', '010', false],
    'neta pulando nível' => ['010.1.1', '010', false],
]);
