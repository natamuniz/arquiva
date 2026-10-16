<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('document')) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        /** @var array<string, list<string>> $types */
        $types = config('arquiva.uploads.types');

        return [
            // Só a extensão: o conteúdo real é verificado no job. "mimes" inspecionaria o conteúdo aqui.
            'file' => [
                'required',
                'file',
                'extensions:'.implode(',', array_keys($types)),
                'max:'.config('arquiva.uploads.max_kilobytes'),
            ],
        ];
    }
}
