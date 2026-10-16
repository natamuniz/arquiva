<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\Secrecy;
use App\Observers\AuditObserver;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $title
 * @property string|null $subject
 * @property string|null $interested_party
 * @property Carbon|null $produced_at
 * @property int|null $classification_id
 * @property int|null $retention_rule_id
 * @property int $unit_id
 * @property Secrecy $secrecy
 * @property DocumentStatus $status
 * @property Carbon|null $captured_at
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[ObservedBy(AuditObserver::class)]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'title', 'subject', 'interested_party', 'produced_at', 'classification_id',
        'retention_rule_id', 'unit_id', 'secrecy', 'status', 'captured_at', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'produced_at' => 'date',
            'captured_at' => 'datetime',
            'secrecy' => Secrecy::class,
            'status' => DocumentStatus::class,
        ];
    }

    /** @return BelongsTo<Classification, $this> */
    public function classification(): BelongsTo
    {
        return $this->belongsTo(Classification::class);
    }

    /** @return BelongsTo<RetentionRule, $this> */
    public function retentionRule(): BelongsTo
    {
        return $this->belongsTo(RetentionRule::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<DocumentFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class)->orderBy('created_at');
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Rascunho;
    }

    /**
     * Metadados visíveis: admin vê tudo; os demais veem documentos públicos e os da própria unidade.
     *
     * @param  Builder<Document>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->where(function (Builder $q) use ($user): void {
            $q->where('documents.secrecy', Secrecy::Publico->value);

            if ($user->unit_id !== null) {
                $q->orWhere('documents.unit_id', $user->unit_id);
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->isAdmin()
            || $this->secrecy === Secrecy::Publico
            || $user->belongsToUnit($this->unit_id);
    }
}
