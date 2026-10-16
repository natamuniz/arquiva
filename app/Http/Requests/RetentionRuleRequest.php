<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FinalDestination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Usado para criar a primeira regra e para criar uma nova versão. */
class RetentionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-classification-plan');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'current_years' => ['required', 'integer', 'min:0', 'max:200'],
            'intermediate_years' => ['required', 'integer', 'min:0', 'max:200'],
            'final_destination' => ['required', Rule::enum(FinalDestination::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array{current_years: int, intermediate_years: int, final_destination: string, notes: string|null} */
    public function ruleData(): array
    {
        return [
            'current_years' => $this->integer('current_years'),
            'intermediate_years' => $this->integer('intermediate_years'),
            'final_destination' => $this->string('final_destination')->toString(),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
        ];
    }
}
