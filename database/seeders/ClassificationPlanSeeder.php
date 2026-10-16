<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Classifications\ImportClassificationsFromCsv;
use Illuminate\Database\Seeder;

/** Usa a mesma importação CSV da API, com um plano fictício. */
class ClassificationPlanSeeder extends Seeder
{
    public function run(ImportClassificationsFromCsv $import): void
    {
        $import->handle((string) file_get_contents(database_path('data/plano-classificacao.csv')));
    }
}
