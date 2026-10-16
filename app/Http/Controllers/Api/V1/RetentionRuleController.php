<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\RetentionRules\CreateRetentionRule;
use App\Actions\RetentionRules\ReviseRetentionRule;
use App\Http\Controllers\Controller;
use App\Http\Requests\RetentionRuleRequest;
use App\Http\Resources\RetentionRuleResource;
use App\Models\Classification;
use App\Models\RetentionRule;
use App\Models\User;
use App\Support\ReferenceDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @group Tabela de temporalidade
 */
class RetentionRuleController extends Controller
{
    /**
     * Histórico de regras da classe
     *
     * Sem parâmetros, lista todas as versões. Com `at`, devolve só a versão vigente naquele instante
     * (404 se não havia regra).
     *
     * @queryParam at string Data (AAAA-MM-DD, 00:00 em America/Sao_Paulo) ou data-hora ISO 8601. Example: 2025-01-10
     */
    public function index(Request $request, Classification $classification): AnonymousResourceCollection|RetentionRuleResource
    {
        if ($request->filled('at')) {
            $at = ReferenceDate::parse($request->string('at')->toString(), 'at');

            $rule = $classification->retentionRules()->validAt($at)->first();

            if ($rule === null) {
                throw new NotFoundHttpException('Não havia regra de temporalidade vigente para esta classe nessa data.');
            }

            return new RetentionRuleResource($rule);
        }

        return RetentionRuleResource::collection($classification->retentionRules()->orderBy('version')->get());
    }

    /**
     * Criar primeira regra da classe
     *
     * Cria a versão 1. Se a classe já tem regra vigente, responde 409: use a criação de nova versão.
     */
    public function store(RetentionRuleRequest $request, Classification $classification, CreateRetentionRule $create): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rule = $create->handle($classification, $request->ruleData(), $user);

        return (new RetentionRuleResource($rule))->response()->setStatusCode(201);
    }

    /**
     * Detalhar versão
     */
    public function show(RetentionRule $retentionRule): RetentionRuleResource
    {
        return new RetentionRuleResource($retentionRule);
    }

    /**
     * Alterar regra (nova versão)
     *
     * Encerra a versão vigente e cria a próxima. Versão encerrada responde 409.
     */
    public function update(RetentionRuleRequest $request, RetentionRule $retentionRule, ReviseRetentionRule $revise): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rule = $revise->handle($retentionRule, $request->ruleData(), $user);

        return (new RetentionRuleResource($rule))->response()->setStatusCode(201);
    }
}
