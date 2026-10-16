<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Gestor = 'gestor';
    case Operador = 'operador';
    case Consulta = 'consulta';

    /** Perfis que criam, editam e capturam documentos. */
    public function canWriteDocuments(): bool
    {
        return $this !== self::Consulta;
    }

    /** Perfis que gerenciam o plano de classificação e a temporalidade. */
    public function canManageClassificationPlan(): bool
    {
        return $this === self::Admin || $this === self::Gestor;
    }
}
