<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinalDestination;
use App\Observers\AuditObserver;
use Carbon\CarbonInterface;
use Database\Factories\RetentionRuleFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $classification_id
 * @property int $version
 * @property int $current_years
 * @property int $intermediate_years
 * @property FinalDestination $final_destination
 * @property string|null $notes
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property int|null $created_by
 */
#[ObservedBy(AuditObserver::class)]
class RetentionRule extends Model
{
    /** @use HasFactory<RetentionRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'classification_id', 'version', 'current_years', 'intermediate_years',
        'final_destination', 'notes', 'valid_from', 'valid_to', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'current_years' => 'integer',
            'intermediate_years' => 'integer',
            'final_destination' => FinalDestination::class,
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    /** @return BelongsTo<Classification, $this> */
    public function classification(): BelongsTo
    {
        return $this->belongsTo(Classification::class);
    }

    public function isCurrent(): bool
    {
        return $this->valid_to === null;
    }

    /**
     * Versão vigente em um instante: valid_from <= at e (valid_to nulo ou at < valid_to).
     *
     * @param  Builder<RetentionRule>  $query
     */
    public function scopeValidAt(Builder $query, CarbonInterface $at): void
    {
        $query->where('valid_from', '<=', $at)
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $at));
    }
}
