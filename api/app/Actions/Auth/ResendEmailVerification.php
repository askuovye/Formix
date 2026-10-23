<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;

final class ResendEmailVerification
{
    public function __invoke(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
