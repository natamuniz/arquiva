<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditEventResource;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\ReferenceDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Auditoria
 */
class AuditEventController extends Controller
{
    /**
     * Trilha geral
     *
     * Para admin e gestor. Eventos de documentos que o usuário não pode ver ficam de fora.
     *
     * @queryParam user_id integer Autor do evento. Example: 1
     * @queryParam action string Ação exata. Example: document.updated
     * @queryParam auditable_type string Entidade: document, file, classification, retention_rule ou user. Example: document
     * @queryParam auditable_id string Id da entidade. Example: 9d2f...
     * @queryParam from string Início do período (data ou data-hora). Example: 2026-01-01
     * @queryParam to string Fim do período, exclusivo (data ou data-hora). Example: 2026-12-31
     * @queryParam per_page integer Itens por página (até 100). Example: 20
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('view-audit-trail');

        $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:60'],
            'auditable_type' => ['nullable', 'string', 'max:40'],
            'auditable_id' => ['nullable', 'string', 'max:36'],
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $query = AuditEvent::query()->visibleTo($user);

        foreach (['user_id', 'action', 'auditable_type', 'auditable_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', ReferenceDate::parse($request->string('from')->toString(), 'from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<', ReferenceDate::parse($request->string('to')->toString(), 'to'));
        }

        $events = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 20));

        return AuditEventResource::collection($events->withQueryString());
    }
}
