<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Classifications\ActivateClassification;
use App\Actions\Classifications\CreateClassification;
use App\Actions\Classifications\DeactivateClassification;
use App\Actions\Classifications\DeleteClassification;
use App\Actions\Classifications\ImportClassificationsFromCsv;
use App\Actions\Classifications\UpdateClassification;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportClassificationsRequest;
use App\Http\Requests\StoreClassificationRequest;
use App\Http\Requests\UpdateClassificationRequest;
use App\Http\Resources\ClassificationResource;
use App\Models\Classification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * @group Plano de classificação
 */
class ClassificationController extends Controller
{
    /**
     * Plano completo em árvore
     *
     * Devolve todas as classes aninhadas, ordenadas por código.
     */
    public function index(): AnonymousResourceCollection
    {
        $nodes = Classification::query()->orderByCode()->get();

        return ClassificationResource::collection(Classification::buildForest($nodes));
    }

    /**
     * Detalhar classe
     */
    public function show(Classification $classification): ClassificationResource
    {
        return new ClassificationResource($classification);
    }

    /**
     * Subárvore de uma classe
     *
     * A classe com todas as descendentes aninhadas (consulta recursiva no PostgreSQL).
     */
    public function tree(Classification $classification): ClassificationResource
    {
        return new ClassificationResource($classification->loadSubtree());
    }

    /**
     * Criar classe
     *
     * Raiz: 3 dígitos (ex.: 010). Filha: código do pai + "." + 1 a 3 dígitos (ex.: 010.1). Perfis: admin e gestor.
     *
     * @bodyParam code string required Código hierárquico. Example: 010.1
     * @bodyParam name string required Nome da classe. Example: Normas e regulamentos
     * @bodyParam parent_id integer Classe pai (vazio para raiz). Example: 1
     */
    public function store(StoreClassificationRequest $request, CreateClassification $create): JsonResponse
    {
        $classification = $create->handle(
            $request->string('code')->toString(),
            $request->string('name')->toString(),
            $request->filled('parent_id') ? $request->integer('parent_id') : null,
        );

        return (new ClassificationResource($classification))->response()->setStatusCode(201);
    }

    /**
     * Renomear classe
     *
     * Só o nome é editável; tentar mudar código ou pai responde 409.
     */
    public function update(UpdateClassificationRequest $request, Classification $classification, UpdateClassification $update): ClassificationResource
    {
        return new ClassificationResource($update->handle($classification, $request->validated()));
    }

    /**
     * Excluir classe
     *
     * Só classes sem filhas, documentos ou regras. Caso contrário, 409 sugerindo desativar.
     */
    public function destroy(Classification $classification, DeleteClassification $delete): Response
    {
        Gate::authorize('manage-classification-plan');

        $delete->handle($classification);

        return response()->noContent();
    }

    /**
     * Desativar classe
     *
     * Classe inativa não recebe novos documentos. Bloqueado (409) se houver filhas ativas.
     */
    public function deactivate(Classification $classification, DeactivateClassification $deactivate): ClassificationResource
    {
        Gate::authorize('manage-classification-plan');

        return new ClassificationResource($deactivate->handle($classification));
    }

    /**
     * Reativar classe
     */
    public function activate(Classification $classification, ActivateClassification $activate): ClassificationResource
    {
        Gate::authorize('manage-classification-plan');

        return new ClassificationResource($activate->handle($classification));
    }

    /**
     * Importar plano por CSV
     *
     * Colunas code,name,parent_code. Tudo ou nada: com qualquer erro, nada é gravado e a resposta lista os erros por linha
     * (o cabeçalho é a linha 1). Aceita separador "," ou ";" e UTF-8 com BOM.
     *
     * @bodyParam file file required Arquivo CSV.
     */
    public function import(ImportClassificationsRequest $request, ImportClassificationsFromCsv $import): JsonResponse
    {
        $file = $request->file('file');
        $contents = $file instanceof UploadedFile ? (string) $file->get() : '';

        return response()->json(['imported' => $import->handle($contents)], 201);
    }
}
