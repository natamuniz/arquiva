<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Dados válidos no formato, mas que não atendem a uma regra de negócio (HTTP 422),
 * com mensagem própria e lista de erros por campo ou por linha.
 */
class UnprocessableException extends RuntimeException
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
