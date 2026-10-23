<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Validation\ValidationException;

/**
 * Não exige login: o link pode ser aberto em outro dispositivo.
 * A assinatura temporária (validada no Form Request) e o hash do email bastam.
 */
final class VerifyUserEmail
{
    public const INVALID_LINK_MESSAGE = 'O link de verificação é inválido ou expirou.';

    public function __invoke(string $userId, string $emailHash): void
    {
        $user = User::query()->find($userId);

        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $emailHash)) {
            throw ValidationException::withMessages(['signature' => self::INVALID_LINK_MESSAGE]);
        }

        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->markEmailAsVerified();
        event(new Verified($user));
    }
}
