<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Exceptions\ConflictException;
use App\Models\Classification;
use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class UpdateDocument
{
    /** @param  array<string, mixed>  $data  dados já validados */
    public function handle(Document $document, array $data, User $user): Document
    {
        $document->fill($data);

        if (! $document->isDraft()) {
            $this->guardCapturedDocument($document, $user);
        } elseif ($document->isDirty('classification_id') && $document->classification_id !== null) {
            $this->guardActiveClassification($document->classification_id);
        }

        $document->save();

        return $document;
    }

    private function guardCapturedDocument(Document $document, User $user): void
    {
        if ($document->isDirty('classification_id') || $document->isDirty('unit_id')) {
            throw new ConflictException(
                'Classe e unidade não podem ser alteradas depois da captura: a regra de temporalidade já foi aplicada.'
            );
        }

        if ($document->isDirty('secrecy') && ! $user->can('changeCapturedSecrecy', $document)) {
            throw new AuthorizationException('Só o gestor da unidade ou o admin altera o sigilo de documento capturado.');
        }
    }

    private function guardActiveClassification(int $classificationId): void
    {
        $classification = Classification::query()->find($classificationId);

        if ($classification === null || ! $classification->active) {
            throw ValidationException::withMessages([
                'classification_id' => [$classification === null
                    ? 'A classe informada não existe.'
                    : "A classe {$classification->code} está inativa e não pode receber novos documentos."],
            ]);
        }
    }
}
