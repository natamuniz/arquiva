<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra criação, alteração e exclusão dos models auditáveis.
 * A ação usa o alias do morph map: "document.updated", "file.created" etc.
 */
class AuditObserver
{
    /** Campos que não entram na trilha. */
    private const IGNORED = ['created_at', 'updated_at', 'password', 'remember_token'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $this->audit->record($model->getMorphClass().'.created', $model, null, $this->filter($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->filter($model->getChanges());

        if ($changes === []) {
            return;
        }

        $before = [];
        foreach (array_keys($changes) as $key) {
            $before[$key] = $model->getRawOriginal($key);
        }

        $this->audit->record($model->getMorphClass().'.updated', $model, $before, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->audit->record($model->getMorphClass().'.deleted', $model, $this->filter($model->getAttributes()), null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filter(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::IGNORED));
    }
}
