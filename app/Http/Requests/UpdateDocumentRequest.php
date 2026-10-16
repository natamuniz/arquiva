<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Secrecy;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('document')) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $user = $this->user();
        // Só o admin escolhe a unidade; os demais criam na própria unidade.
        $isAdmin = $user instanceof User && $user->isAdmin();

        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'interested_party' => ['sometimes', 'nullable', 'string', 'max:255'],
            'produced_at' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            // Se a classe está ativa é verificado no UpdateDocument, depois da regra de captura (409).
            'classification_id' => ['sometimes', 'nullable', 'integer'],
            'secrecy' => ['sometimes', Rule::enum(Secrecy::class)],
            'unit_id' => $isAdmin
                ? ['sometimes', 'integer', 'exists:units,id']
                : ['prohibited'],
        ];
    }
}
