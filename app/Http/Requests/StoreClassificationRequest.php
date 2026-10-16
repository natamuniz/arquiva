<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-classification-plan');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:classifications,code'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:classifications,id'],
        ];
    }
}
