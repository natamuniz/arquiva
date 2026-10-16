<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Files\DeleteDocumentFile;
use App\Actions\Files\ReprocessDocumentFile;
use App\Actions\Files\StoreDocumentFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentFileRequest;
use App\Http\Resources\DocumentFileResource;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @group Arquivos
 */
class DocumentFileController extends Controller
{
    /**
     * Enviar arquivo
     *
     * PDF, PNG, JPEG ou TIFF até 50 MB, só em rascunhos. O arquivo fica "pendente" até o processamento em fila.
     *
     * @bodyParam file file required Arquivo.
     */
    public function store(StoreDocumentFileRequest $request, Document $document, StoreDocumentFile $store): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $upload */
        $upload = $request->file('file');

        $file = $store->handle($document, $upload, $user);

        return (new DocumentFileResource($file->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Detalhar arquivo
     */
    public function show(DocumentFile $file): DocumentFileResource
    {
        return new DocumentFileResource($file);
    }

    /**
     * Baixar arquivo
     *
     * Exige credencial maior ou igual ao sigilo do documento. Todo download é auditado.
     */
    public function download(DocumentFile $file, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('download', $file->document);

        $disk = Storage::disk((string) config('arquiva.uploads.disk'));

        if (! $disk->exists($file->storage_path)) {
            throw new NotFoundHttpException('O arquivo físico não foi encontrado. A verificação de fixidez vai registrar a ausência.');
        }

        $audit->record('file.downloaded', $file);

        return $disk->download($file->storage_path, $file->original_name);
    }

    /**
     * Reprocessar arquivo
     *
     * Só para arquivos com processamento "falhou".
     */
    public function reprocess(DocumentFile $file, ReprocessDocumentFile $reprocess): DocumentFileResource
    {
        Gate::authorize('update', $file->document);

        return new DocumentFileResource($reprocess->handle($file));
    }

    /**
     * Remover arquivo do rascunho
     *
     * Documento capturado responde 409. O arquivo físico é apagado depois do commit.
     */
    public function destroy(Request $request, DocumentFile $file, DeleteDocumentFile $delete): Response
    {
        Gate::authorize('update', $file->document);

        $delete->handle($file);

        return response()->noContent();
    }
}
