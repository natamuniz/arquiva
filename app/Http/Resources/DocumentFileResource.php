<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentFile */
class DocumentFileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'sha256' => $this->sha256,
            'processing_status' => $this->processing_status->value,
            'page_count' => $this->page_count,
            'failure_reason' => $this->failure_reason,
            'fixity_ok' => $this->fixity_ok,
            'last_fixity_check_at' => $this->last_fixity_check_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
