<?php

declare(strict_types=1);

namespace App\Enums;

enum FinalDestination: string
{
    case Eliminacao = 'eliminacao';
    case GuardaPermanente = 'guarda_permanente';
}
