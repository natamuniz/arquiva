<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Documents\SearchDocuments;
use App\Enums\DocumentStatus;
use App\Enums\Secrecy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchDocumentsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $sortable = [];
        foreach (SearchDocuments::SORTABLE as $column) {
            $sortable[] = $column;
            $sortable[] = '-'.$column;
        }

        return [
            'q' => ['nullable', 'string', 'max:200'],
            'classification_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'secrecy' => ['nullable', Rule::enum(Secrecy::class)],
            'status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'produced_from' => ['nullable', 'date_format:Y-m-d'],
            'produced_to' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in($sortable)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
