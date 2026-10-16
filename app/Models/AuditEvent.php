<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\ImmutableAuditEventException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evento da trilha de auditoria. Só pode ser criado: update e delete lançam exceção.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $auditable_type
 * @property string|null $auditable_id
 * @property string|null $document_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip
 * @property Carbon $created_at
 */
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id', 'document_id', 'before', 'after', 'ip',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new ImmutableAuditEventException);
        static::deleting(fn () => throw new ImmutableAuditEventException);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Esconde eventos de documentos que o usuário não pode ver (inclusive de rascunhos já excluídos).
     *
     * @param  Builder<AuditEvent>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->where(function (Builder $q) use ($user): void {
            $q->whereNull('document_id')
                ->orWhereIn('document_id', Document::query()->visibleTo($user)->select('id'));
        });
    }
}
