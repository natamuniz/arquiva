<?php

declare(strict_types=1);

namespace App\Actions\Classifications;

use App\Exceptions\ConflictException;
use App\Models\Classification;

class ActivateClassification
{
    public function handle(Classification $classification): Classification
    {
        $parent = $classification->parent;

        if ($parent !== null && ! $parent->active) {
            throw new ConflictException("A classe pai {$parent->code} está inativa. Ative a classe pai antes.");
        }

        $classification->update(['active' => true]);

        return $classification;
    }
}
