<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckFileFixity;
use App\Models\DocumentFile;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CheckFixity extends Command
{
    protected $signature = 'arquiva:check-fixity {--batch= : Arquivos por job (padrão em config/arquiva.php)}';

    protected $description = 'Enfileira a verificação de fixidez (SHA-256) de todos os arquivos, em lotes';

    public function handle(): int
    {
        $batchSize = max(1, (int) ($this->option('batch') ?: config('arquiva.fixity.batch_size')));
        $jobs = 0;
        $files = 0;

        DocumentFile::query()->select('id')->orderBy('id')->chunkById($batchSize, function (Collection $chunk) use (&$jobs, &$files): void {
            /** @var list<string> $ids */
            $ids = $chunk->pluck('id')->map(fn (mixed $id): string => (string) $id)->values()->all();

            CheckFileFixity::dispatch($ids);
            $jobs++;
            $files += count($ids);
        });

        $this->info("{$files} arquivo(s) enviados para verificação em {$jobs} lote(s).");

        return self::SUCCESS;
    }
}
