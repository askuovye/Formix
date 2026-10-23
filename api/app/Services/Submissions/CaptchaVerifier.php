<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use Illuminate\Http\Request;

/**
 * Ponto de extensão para CAPTCHA nas rotas públicas (decisão futura, AGENTS.md §6).
 * Para ativar: implementar esta interface (ex.: Cloudflare Turnstile) e trocar o
 * binding em AppServiceProvider. O envio de resposta já chama `verify()`.
 */
interface CaptchaVerifier
{
    public function verify(Request $request): bool;
}
