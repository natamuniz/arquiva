<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentStatus: string
{
    case Rascunho = 'rascunho';
    case Capturado = 'capturado';
}
