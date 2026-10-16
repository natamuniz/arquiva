<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CaptureDocument;
use App\Actions\Documents\CreateDraftDocument;
use App\Actions\Documents\DeleteDraftDocument;
use App\Actions\Documents\SearchDocuments;
use App\Actions\Documents\UpdateDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchDocumentsRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Resources\AuditEventResource;
use App\Http\Resources\DocumentResource;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * @group Documentos
 *
 * Documentos que o usuário não pode ver respondem 404 em qualquer rota (não revelamos que existem).
 */
class DocumentController extends Controller
{
    /**
     * Buscar documentos
     *
     * Lista os documentos visíveis ao usuário, 20 por página.
     *
     * @queryParam q string Texto em título, assunto ou interessado (ignora maiúsculas e acentos). Example: licitacao
     * @queryParam classification_id integer Classe, incluindo as descendentes. Example: 1
     * @queryParam unit_id integer Unidade dona. Example: 2
     * @queryParam secrecy string publico, restrito ou sigiloso. Example: publico
     * @queryParam status string rascunho ou capturado. Example: capturado
     * @queryParam produced_from string Data de produção inicial (AAAA-MM-DD). Example: 2024-01-01
     * @queryParam produced_to string Data de produção final (AAAA-MM-DD). Example: 2024-12-31
     * @queryParam sort string title, produced_at, captured_at ou created_at; prefixo "-" para decrescente. Example: -produced_at
     * @queryParam per_page integer Itens por página (até 100). Example: 20
     */
    public function index(SearchDocumentsRequest $request, SearchDocuments $search): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return DocumentResource::collection($search->handle($user, $request->validated()));
    }

    /**
     * Criar rascunho
     *
     * A unidade é a do usuário (o admin informa `unit_id`). Sigilo padrão: restrito.
     *
     * @bodyParam title string Título. Example: Contrato de prestação de serviços de limpeza
     * @bodyParam subject string Assunto. Example: Contratação de empresa de limpeza para a sede
     * @bodyParam interested_party string Interessado. Example: Limpa Tudo Serviços Ltda.
     * @bodyParam produced_at string Data de produção (AAAA-MM-DD, não futura). Example: 2024-03-15
     * @bodyParam classification_id integer Classe ativa. Example: 5
     * @bodyParam secrecy string publico, restrito ou sigiloso. Example: restrito
     * @bodyParam unit_id integer Só para admin. Example: 1
     */
    public function store(StoreDocumentRequest $request, CreateDraftDocument $create): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $document = $create->handle($user, $request->validated());

        return (new DocumentResource($document->load(['classification', 'unit'])))->response()->setStatusCode(201);
    }

    /**
     * Detalhar documento
     */
    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document->load(['classification', 'unit', 'retentionRule', 'files']));
    }

    /**
     * Editar documento
     *
     * Depois da captura, classe e unidade não mudam (409) e só gestor da unidade ou admin altera o sigilo.
     */
    public function update(UpdateDocumentRequest $request, Document $document, UpdateDocument $update): DocumentResource
    {
        /** @var User $user */
        $user = $request->user();

        $document = $update->handle($document, $request->validated(), $user);

        return new DocumentResource($document->load(['classification', 'unit', 'retentionRule', 'files']));
    }

    /**
     * Excluir rascunho
     *
     * Documento capturado responde 409. Os arquivos físicos são apagados depois do commit.
     */
    public function destroy(Document $document, DeleteDraftDocument $delete): Response
    {
        Gate::authorize('delete', $document);

        $delete->handle($document);

        return response()->noContent();
    }

    /**
     * Capturar documento
     *
     * Exige título, data de produção, classe ativa com regra vigente e todos os arquivos processados.
     * Lista todas as pendências de uma vez (422). Guarda a versão vigente da regra de temporalidade.
     */
    public function capture(Document $document, CaptureDocument $capture): DocumentResource
    {
        Gate::authorize('capture', $document);

        $document = $capture->handle($document);

        return new DocumentResource($document->load(['classification', 'unit', 'retentionRule', 'files']));
    }

    /**
     * Histórico do documento
     *
     * Eventos de auditoria do documento e dos seus arquivos, em ordem cronológica.
     */
    public function history(Request $request, Document $document): AnonymousResourceCollection
    {
        $events = AuditEvent::query()
            ->where('document_id', $document->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(50);

        return AuditEventResource::collection($events);
    }
}
