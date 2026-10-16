<?php

declare(strict_types=1);

namespace App\Enums;

enum Secrecy: string
{
    case Publico = 'publico';
    case Restrito = 'restrito';
    case Sigiloso = 'sigiloso';

    /** Ordem de sigilo: publico < restrito < sigiloso. */
    public function rank(): int
    {
        return match ($this) {
            self::Publico => 1,
            self::Restrito => 2,
            self::Sigiloso => 3,
        };
    }

    /** Uma credencial cobre um sigilo quando é maior ou igual a ele. */
    public function covers(self $secrecy): bool
    {
        return $this->rank() >= $secrecy->rank();
    }
}
