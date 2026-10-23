<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use Illuminate\Http\Request;

/**
 * Padrão enquanto não há CAPTCHA: honeypot e rate limit fazem a proteção anti-bot.
 */
final class NullCaptchaVerifier implements CaptchaVerifier
{
    public function verify(Request $request): bool
    {
        return true;
    }
}
