<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Classification;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A classe precisa existir e estar ativa: classes inativas não recebem novos documentos. */
class ActiveClassification implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            $fail('A classe informada é inválida.');

            return;
        }

        $classification = Classification::query()->whereKey($value)->first();

        if ($classification === null) {
            $fail('A classe informada não existe.');

            return;
        }

        if (! $classification->active) {
            $fail("A classe {$classification->code} está inativa e não pode receber novos documentos.");
        }
    }
}
