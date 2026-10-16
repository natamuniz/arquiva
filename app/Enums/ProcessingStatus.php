<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessingStatus: string
{
    case Pendente = 'pendente';
    case Processado = 'processado';
    case Falhou = 'falhou';
}
