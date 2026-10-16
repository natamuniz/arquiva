<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-classification-plan');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            // Aceitos só para devolver 409 quando alguém tenta mudar: código e pai são imutáveis.
            'code' => ['sometimes', 'nullable'],
            'parent_id' => ['sometimes', 'nullable'],
        ];
    }
}
