<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Unidade '.fake()->unique()->word(),
            'code' => strtoupper(fake()->unique()->bothify('U??##')),
        ];
    }
}
