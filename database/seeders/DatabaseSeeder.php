<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Dados 100% fictícios para demonstração. Senha de todos os usuários: "password". */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UnitsAndUsersSeeder::class,
            ClassificationPlanSeeder::class,
            RetentionScheduleSeeder::class,
            SampleDocumentsSeeder::class,
        ]);
    }
}
