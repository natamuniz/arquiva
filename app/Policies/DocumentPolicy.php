<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Document;
use App\Models\User;

/**
 * A visibilidade (404) é resolvida antes, na busca do documento pela rota.
 * Aqui ficam só as permissões de ação (403) sobre documentos que o usuário já enxerga.
 */
class DocumentPolicy
{
    public function create(User $user): bool
    {
        return $user->role->canWriteDocuments() && ($user->isAdmin() || $user->unit_id !== null);
    }

    public function update(User $user, Document $document): bool
    {
        return $user->role->canWriteDocuments()
            && ($user->isAdmin() || $user->belongsToUnit($document->unit_id));
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function capture(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    /** Alterar o sigilo de documento capturado: só gestor da unidade ou admin. */
    public function changeCapturedSecrecy(User $user, Document $document): bool
    {
        return $user->isAdmin()
            || ($user->role === Role::Gestor && $user->belongsToUnit($document->unit_id));
    }

    /** Baixar arquivo: ver os metadados E ter credencial >= sigilo. Admin sempre pode. */
    public function download(User $user, Document $document): bool
    {
        return $user->isAdmin()
            || ($document->isVisibleTo($user) && $user->clearance->covers($document->secrecy));
    }
}
