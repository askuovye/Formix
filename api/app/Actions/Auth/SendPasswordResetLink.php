<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Password;

final class SendPasswordResetLink
{
    /**
     * O status do broker (enviado, usuário inexistente, limite atingido) é
     * descartado de propósito: a API responde sempre igual para não revelar contas.
     */
    public function __invoke(string $email): void
    {
        Password::broker()->sendResetLink(['email' => $email]);
    }
}
