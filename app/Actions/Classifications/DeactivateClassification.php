<?php

declare(strict_types=1);

namespace App\Actions\Classifications;

use App\Exceptions\ConflictException;
use App\Models\Classification;

class DeactivateClassification
{
    public function handle(Classification $classification): Classification
    {
        $activeChildren = $classification->children()->where('active', true)->orderByCode()->pluck('code')->all();

        if ($activeChildren !== []) {
            throw new ConflictException(
                'A classe tem filhas ativas. Desative as filhas antes.',
                ['children' => array_values(array_map('strval', $activeChildren))],
            );
        }

        $classification->update(['active' => false]);

        return $classification;
    }
}
