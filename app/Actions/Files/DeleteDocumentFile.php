<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Exceptions\ConflictException;
use App\Models\DocumentFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteDocumentFile
{
    /** Banco antes do disco: o arquivo físico só é apagado depois do commit. */
    public function handle(DocumentFile $file): void
    {
        if (! $file->document->isDraft()) {
            throw new ConflictException('Arquivos de documento capturado não podem ser removidos.');
        }

        DB::transaction(function () use ($file): void {
            $path = $file->storage_path;
            $file->delete();

            DB::afterCommit(fn () => Storage::disk((string) config('arquiva.uploads.disk'))->delete($path));
        });
    }
}
