<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ImportClassificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-classification-plan');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:5120'],
        ];
    }
}
