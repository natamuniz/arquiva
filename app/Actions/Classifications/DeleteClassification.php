<?php

declare(strict_types=1);

namespace App\Actions\Classifications;

use App\Exceptions\ConflictException;
use App\Models\Classification;

class DeleteClassification
{
    public function handle(Classification $classification): void
    {
        if ($classification->children()->exists()) {
            throw new ConflictException('A classe possui classes filhas e não pode ser excluída. Desative-a em vez de excluir.');
        }

        if ($classification->documents()->exists()) {
            throw new ConflictException('A classe possui documentos e não pode ser excluída. Desative-a em vez de excluir.');
        }

        if ($classification->retentionRules()->exists()) {
            throw new ConflictException('A classe possui regras de temporalidade e não pode ser excluída. Desative-a em vez de excluir.');
        }

        $classification->delete();
    }
}
