<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\Secrecy;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class UnitsAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        $units = [];

        foreach (['PRES' => 'Presidência', 'ADM' => 'Diretoria Administrativa', 'JUR' => 'Assessoria Jurídica'] as $code => $name) {
            $units[$code] = Unit::query()->create(['code' => $code, 'name' => $name]);
        }

        $users = [
            ['admin@arquiva.test', 'Ana Admin', Role::Admin, 'PRES', Secrecy::Sigiloso],
            ['gestor.adm@arquiva.test', 'Gustavo Gestor', Role::Gestor, 'ADM', Secrecy::Sigiloso],
            ['operador.adm@arquiva.test', 'Olívia Operadora', Role::Operador, 'ADM', Secrecy::Restrito],
            ['consulta.adm@arquiva.test', 'Caio Consulta', Role::Consulta, 'ADM', Secrecy::Publico],
            ['operador.jur@arquiva.test', 'Júlia Jurídico', Role::Operador, 'JUR', Secrecy::Sigiloso],
        ];

        foreach ($users as [$email, $name, $role, $unit, $clearance]) {
            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => 'password',
                'role' => $role,
                'unit_id' => $units[$unit]->id,
                'clearance' => $clearance,
            ]);
        }
    }
}
