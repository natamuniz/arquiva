<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Enums\Secrecy;
use App\Models\Document;
use App\Models\User;

class CreateDraftDocument
{
    /** @param  array<string, mixed>  $data  dados já validados */
    public function handle(User $user, array $data): Document
    {
        return Document::create([
            'title' => $data['title'] ?? null,
            'subject' => $data['subject'] ?? null,
            'interested_party' => $data['interested_party'] ?? null,
            'produced_at' => $data['produced_at'] ?? null,
            'classification_id' => $data['classification_id'] ?? null,
            'secrecy' => $data['secrecy'] ?? Secrecy::Restrito->value,
            // A unidade é sempre a do usuário, exceto para o admin, que a informa.
            'unit_id' => $user->isAdmin() ? $data['unit_id'] : $user->unit_id,
            'status' => DocumentStatus::Rascunho,
            'created_by' => $user->id,
        ]);
    }
}
