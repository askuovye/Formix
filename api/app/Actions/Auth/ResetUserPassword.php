<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ResetUserPassword
{
    public const INVALID_TOKEN_MESSAGE = 'O link de redefinição é inválido ou expirou.';

    /**
     * Trocar a senha encerra as outras sessões: o middleware AuthenticateSession
     * do Sanctum invalida sessões cujo hash de senha não confere mais.
     */
    public function __invoke(string $email, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): void
    {
        $status = Password::broker()->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        // Usuário inexistente e token inválido recebem a mesma resposta.
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => self::INVALID_TOKEN_MESSAGE]);
        }
    }
}
