<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Regra de negócio impede a operação no estado atual do recurso (HTTP 409).
 */
class ConflictException extends RuntimeException
{
    /** @param  array<string, list<string>>  $errors */
    public function __construct(string $message, private readonly array $errors = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
