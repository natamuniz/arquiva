<?php

declare(strict_types=1);

namespace App\Actions\RetentionRules;

use App\Exceptions\ConflictException;
use App\Models\Classification;
use App\Models\RetentionRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateRetentionRule
{
    /**
     * Cria a primeira regra de uma classe (versão 1). Se já houver regra vigente, a alteração
     * deve ser feita criando nova versão.
     *
     * @param  array{current_years: int, intermediate_years: int, final_destination: string, notes: string|null}  $data
     */
    public function handle(Classification $classification, array $data, User $user): RetentionRule
    {
        return DB::transaction(function () use ($classification, $data, $user): RetentionRule {
            if ($classification->currentRetentionRule()->exists()) {
                throw new ConflictException(
                    'A classe já tem uma regra vigente. Para alterar, crie uma nova versão (PUT /api/v1/retention-rules/{id}).'
                );
            }

            $lastVersion = (int) $classification->retentionRules()->max('version');

            // Uma criação simultânea esbarra no índice único parcial e vira 409.
            return RetentionRule::create([
                ...$data,
                'classification_id' => $classification->id,
                'version' => $lastVersion + 1,
                'valid_from' => now(),
                'valid_to' => null,
                'created_by' => $user->id,
            ]);
        });
    }
}
