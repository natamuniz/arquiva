<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Secrecy;
use App\Models\Document;
use App\Models\User;
use App\Rules\ActiveClassification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $user = $this->user();
        // Só o admin escolhe a unidade; os demais criam na própria unidade.
        $isAdmin = $user instanceof User && $user->isAdmin();

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:5000'],
            'interested_party' => ['nullable', 'string', 'max:255'],
            'produced_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'classification_id' => ['nullable', 'integer', new ActiveClassification],
            'secrecy' => ['nullable', Rule::enum(Secrecy::class)],
            'unit_id' => $isAdmin
                ? ['required', 'integer', 'exists:units,id']
                : ['prohibited'],
        ];
    }
}
