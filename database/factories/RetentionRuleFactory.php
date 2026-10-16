<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FinalDestination;
use App\Models\Classification;
use App\Models\RetentionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RetentionRule> */
class RetentionRuleFactory extends Factory
{
    protected $model = RetentionRule::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'classification_id' => Classification::factory(),
            'version' => 1,
            'current_years' => 2,
            'intermediate_years' => 5,
            'final_destination' => FinalDestination::Eliminacao,
            'notes' => null,
            'valid_from' => now()->subYear(),
            'valid_to' => null,
        ];
    }
}
