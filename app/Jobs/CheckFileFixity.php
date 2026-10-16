<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocumentFile;
use App\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Recalcula o SHA-256 de um lote de arquivos e compara com o hash gravado no upload.
 * Divergência ou ausência gera evento "file.fixity_failed" na auditoria.
 */
class CheckFileFixity implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param  list<string>  $fileIds */
    public function __construct(public readonly array $fileIds) {}

    public function handle(AuditLogger $audit): void
    {
        $disk = Storage::disk((string) config('arquiva.uploads.disk'));

        DocumentFile::query()->whereIn('id', $this->fileIds)->each(function (DocumentFile $file) use ($disk, $audit): void {
            $failure = null;
            $actual = null;

            if (! $disk->exists($file->storage_path)) {
                $failure = 'arquivo ausente';
            } else {
                $actual = hash_file('sha256', $disk->path($file->storage_path));

                if ($actual !== $file->sha256) {
                    $failure = 'hash divergente';
                }
            }

            // Sem passar pelo AuditObserver: a verificação diária não deve encher a trilha de "file.updated".
            $file->forceFill([
                'fixity_ok' => $failure === null,
                'last_fixity_check_at' => now(),
            ])->saveQuietly();

            if ($failure !== null) {
                $audit->record('file.fixity_failed', $file, ['sha256' => $file->sha256], [
                    'reason' => $failure,
                    'sha256' => $actual === false ? null : $actual,
                ]);
            }
        });
    }
}
