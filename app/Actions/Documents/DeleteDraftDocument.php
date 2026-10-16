<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Exceptions\ConflictException;
use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteDraftDocument
{
    /**
     * Banco antes do disco: o registro sai dentro da transação e os arquivos físicos só são
     * apagados depois do commit. Se a transação falhar, os arquivos continuam no disco.
     */
    public function handle(Document $document): void
    {
        if (! $document->isDraft()) {
            throw new ConflictException('Somente rascunhos podem ser excluídos. Documentos capturados são permanentes.');
        }

        DB::transaction(function () use ($document): void {
            $files = $document->files()->get();
            $paths = $files->pluck('storage_path')->all();

            // Um a um, para cada arquivo gerar seu evento de auditoria.
            $files->each(fn (DocumentFile $file) => $file->delete());
            $document->delete();

            DB::afterCommit(function () use ($paths): void {
                Storage::disk((string) config('arquiva.uploads.disk'))->delete($paths);
            });
        });
    }
}
