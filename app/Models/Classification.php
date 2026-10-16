<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\AuditObserver;
use Database\Factories\ClassificationFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $parent_id
 * @property int $depth
 * @property bool $active
 */
#[ObservedBy(AuditObserver::class)]
class Classification extends Model
{
    /** @use HasFactory<ClassificationFactory> */
    use HasFactory;

    /** Raiz: 3 dígitos. Filha: código do pai + "." + 1 a 3 dígitos. */
    public const ROOT_CODE_PATTERN = '/^\d{3}$/';

    public const CHILD_SEGMENT_PATTERN = '\.\d{1,3}';

    protected $fillable = ['code', 'name', 'parent_id', 'depth', 'active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'depth' => 'integer',
        ];
    }

    /** @return BelongsTo<Classification, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Classification, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<RetentionRule, $this> */
    public function retentionRules(): HasMany
    {
        return $this->hasMany(RetentionRule::class);
    }

    /** @return HasOne<RetentionRule, $this> */
    public function currentRetentionRule(): HasOne
    {
        return $this->hasOne(RetentionRule::class)->whereNull('valid_to');
    }

    public static function isValidCode(string $code, ?string $parentCode): bool
    {
        if ($parentCode === null) {
            return preg_match(self::ROOT_CODE_PATTERN, $code) === 1;
        }

        return preg_match('/^'.preg_quote($parentCode, '/').self::CHILD_SEGMENT_PATTERN.'$/', $code) === 1;
    }

    /**
     * Ordena pelo código como lista de números ("010.2" antes de "010.10").
     *
     * @param  Builder<Classification>  $query
     */
    public function scopeOrderByCode(Builder $query): void
    {
        $query->orderByRaw("string_to_array(code, '.')::int[]");
    }

    /**
     * SQL da CTE recursiva que devolve o id da classe e de todas as descendentes.
     * Usada como subconsulta: "classification_id IN (<sql>)" com o id como binding.
     *
     * @return literal-string
     */
    public static function subtreeIdsSql(): string
    {
        return 'WITH RECURSIVE subtree AS ('
            .' SELECT id FROM classifications WHERE id = ?'
            .' UNION ALL'
            .' SELECT c.id FROM classifications c JOIN subtree s ON c.parent_id = s.id'
            .') SELECT id FROM subtree';
    }

    /**
     * Carrega a subárvore com uma única consulta recursiva e monta o aninhamento em memória.
     * Cada classe da coleção devolvida tem a relação "children" preenchida.
     */
    public function loadSubtree(): self
    {
        /** @var Collection<int, Classification> $nodes */
        $nodes = self::query()
            ->fromRaw('('
                .'WITH RECURSIVE subtree AS ('
                .' SELECT * FROM classifications WHERE id = ?'
                .' UNION ALL'
                .' SELECT c.* FROM classifications c JOIN subtree s ON c.parent_id = s.id'
                .') SELECT * FROM subtree) AS classifications', [$this->id])
            ->orderByCode()
            ->get();

        $root = self::buildForest($nodes, $this->parent_id)->first();

        return $root ?? $this->setRelation('children', new Collection);
    }

    /**
     * Monta a árvore a partir de uma lista plana já ordenada por código.
     *
     * @param  Collection<int, Classification>  $nodes
     * @return Collection<int, Classification>
     */
    public static function buildForest(Collection $nodes, ?int $rootParentId = null): Collection
    {
        $byParent = $nodes->groupBy(fn (Classification $node): string => (string) $node->parent_id);

        $attach = function (Classification $node) use (&$attach, $byParent): Classification {
            /** @var \Illuminate\Support\Collection<int, Classification> $children */
            $children = $byParent->get((string) $node->id, collect());

            return $node->setRelation('children', new Collection($children->map($attach)->all()));
        };

        /** @var \Illuminate\Support\Collection<int, Classification> $roots */
        $roots = $byParent->get((string) $rootParentId, collect());

        return new Collection($roots->map($attach)->all());
    }
}
