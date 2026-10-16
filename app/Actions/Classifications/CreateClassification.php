<?php

declare(strict_types=1);

namespace App\Actions\Classifications;

use App\Models\Classification;
use Illuminate\Validation\ValidationException;

class CreateClassification
{
    public function handle(string $code, string $name, ?int $parentId): Classification
    {
        $parent = $parentId === null ? null : Classification::query()->findOrFail($parentId);

        if (! Classification::isValidCode($code, $parent?->code)) {
            throw ValidationException::withMessages([
                'code' => [$parent === null
                    ? "O código {$code} é inválido: uma classe raiz deve ter exatamente 3 dígitos (ex.: 010)."
                    : "O código {$code} é incompatível com a classe pai {$parent->code}: esperado {$parent->code}.N, com N de 1 a 3 dígitos."],
            ]);
        }

        return Classification::create([
            'code' => $code,
            'name' => $name,
            'parent_id' => $parent?->id,
            'depth' => $parent === null ? 1 : $parent->depth + 1,
            'active' => true,
        ]);
    }
}
