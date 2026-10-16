<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\Secrecy;
use App\Models\Document;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->sentence(4), '.'),
            'subject' => fake()->sentence(),
            'interested_party' => fake()->company(),
            'produced_at' => fake()->dateTimeBetween('-3 years', '-1 day')->format('Y-m-d'),
            'classification_id' => null,
            'unit_id' => Unit::factory(),
            'secrecy' => Secrecy::Restrito,
            'status' => DocumentStatus::Rascunho,
        ];
    }

    public function secrecy(Secrecy $secrecy): static
    {
        return $this->state(['secrecy' => $secrecy]);
    }

    public function inUnit(Unit $unit): static
    {
        return $this->state(['unit_id' => $unit->id]);
    }

    public function captured(): static
    {
        return $this->state(['status' => DocumentStatus::Capturado, 'captured_at' => now()]);
    }
}
