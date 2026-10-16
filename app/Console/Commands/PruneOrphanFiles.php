<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DocumentFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * O upload grava o disco antes do banco. Se o processo morrer entre os dois, sobra um arquivo
 * sem registro. Este comando apaga essas sobras depois de um prazo de segurança (uploads em andamento ficam).
 */
class PruneOrphanFiles extends Command
{
    protected $signature = 'arquiva:prune-orphans {--dry-run : Só lista, sem apagar}';

    protected $description = 'Remove do disco privado arquivos sem registro no banco, criados há mais de 24 horas';

    public function handle(): int
    {
        $disk = Storage::disk((string) config('arquiva.uploads.disk'));
        $limit = now()->subHours((int) config('arquiva.orphan_grace_hours'))->getTimestamp();
        $removed = 0;

        $paths = collect($disk->allFiles('documents'));

        foreach ($paths->chunk(500) as $chunk) {
            $known = DocumentFile::query()->whereIn('storage_path', $chunk->all())->pluck('storage_path')->flip();

            foreach ($chunk as $path) {
                if ($known->has($path) || $disk->lastModified($path) > $limit) {
                    continue;
                }

                if (! $this->option('dry-run')) {
                    $disk->delete($path);
                }

                $this->line("órfão: {$path}");
                $removed++;
            }
        }

        $verb = $this->option('dry-run') ? 'encontrado(s)' : 'removido(s)';
        $this->info("{$removed} arquivo(s) órfão(s) {$verb}.");

        return self::SUCCESS;
    }
}
