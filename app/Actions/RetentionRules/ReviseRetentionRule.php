<?php

declare(strict_types=1);

namespace App\Actions\RetentionRules;

use App\Exceptions\ConflictException;
use App\Models\RetentionRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviseRetentionRule
{
    /**
     * Alterar uma regra = encerrar a versão vigente e criar a próxima, na mesma transação.
     * O mesmo instante fecha uma e abre a outra, então nunca há buraco nem sobreposição.
     *
     * @param  array{current_years: int, intermediate_years: int, final_destination: string, notes: string|null}  $data
     */
    public function handle(RetentionRule $rule, array $data, User $user): RetentionRule
    {
        return DB::transaction(function () use ($rule, $data, $user): RetentionRule {
            // Trava a linha: uma segunda alteração simultânea espera e depois vê a versão já encerrada.
            $current = RetentionRule::query()->lockForUpdate()->findOrFail($rule->id);

            if (! $current->isCurrent()) {
                throw new ConflictException(
                    "A versão {$current->version} está encerrada e não pode ser alterada. Versões antigas são imutáveis."
                );
            }

            $now = now();

            $current->update(['valid_to' => $now]);

            return RetentionRule::create([
                ...$data,
                'classification_id' => $current->classification_id,
                'version' => $current->version + 1,
                'valid_from' => $now,
                'valid_to' => null,
                'created_by' => $user->id,
            ]);
        });
    }
}
