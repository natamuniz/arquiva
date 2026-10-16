<?php

declare(strict_types=1);

namespace App\Actions\Classifications;

use App\Exceptions\ConflictException;
use App\Models\Classification;

class UpdateClassification
{
    /**
     * Só o nome é editável. Mudar código ou pai quebraria os códigos das filhas
     * e a classificação dos documentos já registrados.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Classification $classification, array $data): Classification
    {
        $changesCode = array_key_exists('code', $data) && $data['code'] !== $classification->code;
        $changesParent = array_key_exists('parent_id', $data)
            && ($data['parent_id'] === null ? null : (int) $data['parent_id']) !== $classification->parent_id;

        if ($changesCode || $changesParent) {
            throw new ConflictException(
                'Código e classe pai não podem ser alterados. Para reestruturar o plano, crie classes novas e desative as antigas.'
            );
        }

        if (array_key_exists('name', $data)) {
            $classification->update(['name' => $data['name']]);
        }

        return $classification;
    }
}
