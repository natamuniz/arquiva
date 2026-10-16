<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RetentionRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RetentionRule */
class RetentionRuleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classification_id' => $this->classification_id,
            'version' => $this->version,
            'current_years' => $this->current_years,
            'intermediate_years' => $this->intermediate_years,
            'final_destination' => $this->final_destination->value,
            'notes' => $this->notes,
            'valid_from' => $this->valid_from->toIso8601String(),
            'valid_to' => $this->valid_to?->toIso8601String(),
            'is_current' => $this->isCurrent(),
            'created_by' => $this->created_by,
        ];
    }
}
