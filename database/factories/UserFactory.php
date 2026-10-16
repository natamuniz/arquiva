<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\Secrecy;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => Role::Operador,
            'unit_id' => Unit::factory(),
            'clearance' => Secrecy::Restrito,
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(['role' => $role]);
    }

    public function clearance(Secrecy $clearance): static
    {
        return $this->state(['clearance' => $clearance]);
    }

    public function inUnit(Unit $unit): static
    {
        return $this->state(['unit_id' => $unit->id]);
    }
}
