<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

class ImmutableAuditEventException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Eventos de auditoria são imutáveis: não podem ser alterados nem excluídos.');
    }
}
