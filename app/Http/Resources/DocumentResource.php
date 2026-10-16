<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subject' => $this->subject,
            'interested_party' => $this->interested_party,
            'produced_at' => $this->produced_at?->toDateString(),
            'status' => $this->status->value,
            'secrecy' => $this->secrecy->value,
            'unit_id' => $this->unit_id,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'classification_id' => $this->classification_id,
            'classification' => $this->whenLoaded('classification', fn () => $this->classification === null ? null : [
                'id' => $this->classification->id,
                'code' => $this->classification->code,
                'name' => $this->classification->name,
            ]),
            'retention_rule_id' => $this->retention_rule_id,
            'retention_rule' => new RetentionRuleResource($this->whenLoaded('retentionRule')),
            'captured_at' => $this->captured_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'files' => DocumentFileResource::collection($this->whenLoaded('files')),
        ];
    }
}
