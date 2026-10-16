<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Classification;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SearchDocuments
{
    public const SORTABLE = ['title', 'produced_at', 'captured_at', 'created_at'];

    /**
     * @param  array<string, mixed>  $filters  dados já validados
     * @return LengthAwarePaginator<int, Document>
     */
    public function handle(User $user, array $filters): LengthAwarePaginator
    {
        $query = Document::query()
            ->visibleTo($user)
            ->with(['classification', 'unit']);

        if (filled($filters['q'] ?? null)) {
            $term = '%'.addcslashes((string) $filters['q'], '%_\\').'%';

            // unaccent + ILIKE: busca sem diferenciar maiúsculas e acentos.
            $query->where(function (Builder $q) use ($term): void {
                foreach (['title', 'subject', 'interested_party'] as $column) {
                    $q->orWhereRaw("unaccent(coalesce(documents.{$column}, '')) ILIKE unaccent(?)", [$term]);
                }
            });
        }

        if (filled($filters['classification_id'] ?? null)) {
            // A classe e todas as descendentes, pela mesma CTE recursiva da árvore.
            $query->whereRaw(
                'documents.classification_id IN ('.Classification::subtreeIdsSql().')',
                [(int) $filters['classification_id']],
            );
        }

        foreach (['unit_id', 'secrecy', 'status'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where("documents.{$field}", $filters[$field]);
            }
        }

        if (filled($filters['produced_from'] ?? null)) {
            $query->whereDate('documents.produced_at', '>=', (string) $filters['produced_from']);
        }

        if (filled($filters['produced_to'] ?? null)) {
            $query->whereDate('documents.produced_at', '<=', (string) $filters['produced_to']);
        }

        $query->orderByRaw($this->orderBySql((string) ($filters['sort'] ?? '-produced_at')))->orderBy('documents.id');

        return $query->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();
    }

    /**
     * SQL fixo por opção: nada vindo da requisição é concatenado na consulta.
     *
     * @return literal-string
     */
    private function orderBySql(string $sort): string
    {
        return match ($sort) {
            'title' => 'documents.title ASC NULLS LAST',
            '-title' => 'documents.title DESC NULLS LAST',
            'produced_at' => 'documents.produced_at ASC NULLS LAST',
            'captured_at' => 'documents.captured_at ASC NULLS LAST',
            '-captured_at' => 'documents.captured_at DESC NULLS LAST',
            'created_at' => 'documents.created_at ASC',
            '-created_at' => 'documents.created_at DESC',
            default => 'documents.produced_at DESC NULLS LAST',
        };
    }
}
