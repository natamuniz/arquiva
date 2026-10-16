<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FinalDestination;
use App\Models\Classification;
use App\Models\RetentionRule;
use Illuminate\Database\Seeder;

class RetentionScheduleSeeder extends Seeder
{
    /** Prazos fictícios: [fase corrente, fase intermediária, destinação]. */
    private const RULES = [
        '010' => [5, 5, FinalDestination::GuardaPermanente],
        '020' => [5, 47, FinalDestination::Eliminacao],
        '020.2' => [7, 93, FinalDestination::Eliminacao],
        '030' => [4, 6, FinalDestination::Eliminacao],
        '030.1.2' => [5, 15, FinalDestination::GuardaPermanente],
        '040' => [5, 5, FinalDestination::Eliminacao],
        '050' => [5, 10, FinalDestination::GuardaPermanente],
    ];

    public function run(): void
    {
        Classification::query()->orderBy('code')->each(function (Classification $classification): void {
            [$current, $intermediate, $destination] = $this->ruleFor($classification->code);

            RetentionRule::query()->create([
                'classification_id' => $classification->id,
                'version' => 1,
                'current_years' => $current,
                'intermediate_years' => $intermediate,
                'final_destination' => $destination,
                'notes' => 'Regra fictícia para demonstração.',
                'valid_from' => now()->subYears(2),
            ]);
        });

        // Uma classe com duas versões, para mostrar o histórico e a consulta ?at=.
        $contracts = Classification::query()->where('code', '030.1.2')->firstOrFail();
        $v1 = $contracts->currentRetentionRule()->firstOrFail();
        $switch = now()->subMonths(6);
        $v1->update(['valid_to' => $switch]);

        RetentionRule::query()->create([
            'classification_id' => $contracts->id,
            'version' => 2,
            'current_years' => 10,
            'intermediate_years' => 10,
            'final_destination' => FinalDestination::GuardaPermanente,
            'notes' => 'Prazo ampliado após revisão da tabela (fictício).',
            'valid_from' => $switch,
        ]);
    }

    /** @return array{0: int, 1: int, 2: FinalDestination} */
    private function ruleFor(string $code): array
    {
        // Usa a regra mais específica definida para a classe ou para o ancestral mais próximo.
        $prefix = $code;

        while (true) {
            if (isset(self::RULES[$prefix])) {
                return self::RULES[$prefix];
            }

            if (! str_contains($prefix, '.')) {
                break;
            }

            $prefix = (string) preg_replace('/\.\d+$/', '', $prefix);
        }

        return [2, 3, FinalDestination::Eliminacao];
    }
}
