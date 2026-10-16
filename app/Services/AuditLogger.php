<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Grava eventos na trilha de auditoria. Usado pelo AuditObserver (alterações de models)
 * e diretamente para eventos que não são alteração de model (login, download, fixidez).
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?int $userId = null,
    ): AuditEvent {
        $key = $subject?->getKey();

        return AuditEvent::create([
            'user_id' => $userId ?? $this->currentUserId(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $key === null ? null : (string) $key,
            'document_id' => $this->documentIdOf($subject),
            'before' => $before,
            'after' => $after,
            'ip' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip(),
        ]);
    }

    private function currentUserId(): ?int
    {
        $id = Auth::id();

        return is_numeric($id) ? (int) $id : null;
    }

    private function documentIdOf(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Document => $subject->id,
            $subject instanceof DocumentFile => $subject->document_id,
            default => null,
        };
    }
}
