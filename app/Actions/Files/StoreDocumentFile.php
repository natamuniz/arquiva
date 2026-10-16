<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Enums\ProcessingStatus;
use App\Exceptions\ConflictException;
use App\Jobs\ProcessDocumentFile;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreDocumentFile
{
    /**
     * Grava o arquivo no disco privado com nome UUID, calcula o SHA-256 e registra no banco.
     * Se o banco falhar, o arquivo gravado é removido. O processamento vai para a fila só depois do commit.
     */
    public function handle(Document $document, UploadedFile $upload, User $user): DocumentFile
    {
        if (! $document->isDraft()) {
            throw new ConflictException('Só é possível enviar arquivos para documentos em rascunho.');
        }

        $sha256 = hash_file('sha256', (string) $upload->getRealPath());

        if ($sha256 === false) {
            throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        $duplicate = $document->files()->where('sha256', $sha256)->first(['id', 'original_name']);

        if ($duplicate !== null) {
            throw new ConflictException(
                "Arquivo duplicado: o documento já tem o arquivo {$duplicate->original_name} com o mesmo conteúdo.",
                ['file' => ["sha256 {$sha256} já existe neste documento."]],
            );
        }

        $disk = Storage::disk((string) config('arquiva.uploads.disk'));
        $id = (string) Str::uuid();
        $path = $disk->putFileAs("documents/{$document->id}", $upload, $id);

        if ($path === false) {
            throw new RuntimeException('Não foi possível gravar o arquivo no armazenamento.');
        }

        try {
            return DB::transaction(function () use ($document, $upload, $user, $id, $path, $sha256): DocumentFile {
                $file = DocumentFile::query()->forceCreate([
                    'id' => $id,
                    'document_id' => $document->id,
                    'original_name' => $upload->getClientOriginalName(),
                    'storage_path' => $path,
                    'mime_type' => Str::limit($upload->getClientMimeType(), 100, ''),
                    'size_bytes' => (int) $upload->getSize(),
                    'sha256' => $sha256,
                    'processing_status' => ProcessingStatus::Pendente,
                    'created_by' => $user->id,
                ]);

                ProcessDocumentFile::dispatch($file->id)->afterCommit();

                return $file;
            });
        } catch (Throwable $e) {
            // O disco foi gravado antes do banco: desfaz a gravação para não deixar órfão.
            $disk->delete($path);

            throw $e;
        }
    }
}
