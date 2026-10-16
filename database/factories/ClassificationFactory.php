<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Classification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Classification> */
class ClassificationFactory extends Factory
{
    protected $model = Classification::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(100, 999),
            'name' => rtrim(fake()->sentence(3), '.'),
            'parent_id' => null,
            'depth' => 1,
            'active' => true,
        ];
    }

    /** Filha com o próximo código livre sob o pai (ex.: 010.1, 010.2). */
    public function childOf(Classification $parent, ?int $segment = null): static
    {
        return $this->state(function () use ($parent, $segment): array {
            $segment ??= $parent->children()->count() + 1;

            return [
                'code' => "{$parent->code}.{$segment}",
                'parent_id' => $parent->id,
                'depth' => $parent->depth + 1,
            ];
        });
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
