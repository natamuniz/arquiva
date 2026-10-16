<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Enums\ProcessingStatus;
use App\Exceptions\ConflictException;
use App\Jobs\ProcessDocumentFile;
use App\Models\DocumentFile;
use Illuminate\Support\Facades\DB;

class ReprocessDocumentFile
{
    public function handle(DocumentFile $file): DocumentFile
    {
        if ($file->processing_status !== ProcessingStatus::Falhou) {
            throw new ConflictException('Só arquivos com processamento "falhou" podem ser reprocessados.');
        }

        return DB::transaction(function () use ($file): DocumentFile {
            $file->update(['processing_status' => ProcessingStatus::Pendente, 'failure_reason' => null]);

            ProcessDocumentFile::dispatch($file->id)->afterCommit();

            return $file;
        });
    }
}
