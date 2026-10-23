<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Support\Str;

/**
 * Email é comparado sem diferenciar maiúsculas: `Fulano@x.com` e `fulano@x.com`
 * são a mesma conta (e a mesma chave de rate limit).
 */
trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return list<string>
     */
    protected function emailRules(): array
    {
        return ['required', 'string', 'email:rfc', 'max:255'];
    }
}
