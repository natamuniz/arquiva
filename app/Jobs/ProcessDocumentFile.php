<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ProcessingStatus;
use App\Models\DocumentFile;
use App\Support\PdfPageCounter;
use finfo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Valida o tipo real do conteúdo (assinatura do arquivo, não a extensão), extrai o número de
 * páginas de PDFs e marca o arquivo como "processado" ou "falhou" com o motivo.
 */
class ProcessDocumentFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> segundos entre tentativas */
    public array $backoff = [10, 60];

    public function __construct(public readonly string $fileId) {}

    public function handle(): void
    {
        $file = DocumentFile::query()->find($this->fileId);

        if ($file === null || $file->processing_status !== ProcessingStatus::Pendente) {
            return;
        }

        $disk = Storage::disk((string) config('arquiva.uploads.disk'));

        if (! $disk->exists($file->storage_path)) {
            $this->markFailed($file, 'Arquivo físico não encontrado no armazenamento.');

            return;
        }

        $absolutePath = $disk->path($file->storage_path);
        $detected = (string) (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath);
        $extension = $file->extension();

        /** @var list<string> $expected */
        $expected = config("arquiva.uploads.types.{$extension}", []);

        if (! in_array($detected, $expected, true)) {
            $this->markFailed($file, "O conteúdo do arquivo ({$detected}) não corresponde à extensão .{$extension}.");

            return;
        }

        $pageCount = null;

        if ($extension === 'pdf') {
            try {
                $pageCount = PdfPageCounter::count($absolutePath);
            } catch (RuntimeException $e) {
                $this->markFailed($file, $e->getMessage());

                return;
            }
        }

        $file->update([
            'mime_type' => $detected,
            'page_count' => $pageCount,
            'processing_status' => ProcessingStatus::Processado,
            'failure_reason' => null,
        ]);
    }

    /** Chamado pela fila quando as tentativas acabam (erro inesperado, ex.: banco fora do ar). */
    public function failed(Throwable $exception): void
    {
        $file = DocumentFile::query()->find($this->fileId);

        if ($file !== null) {
            $this->markFailed($file, "Falha após {$this->tries} tentativas: {$exception->getMessage()}");
        }
    }

    private function markFailed(DocumentFile $file, string $reason): void
    {
        $file->update([
            'processing_status' => ProcessingStatus::Falhou,
            'failure_reason' => mb_substr($reason, 0, 2000),
        ]);
    }
}
