<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Recusa corpo maior que o limite (em KB) antes de qualquer processamento.
 * Uso: `LimitRequestSize::class.':1024'`.
 *
 * Em `multipart/form-data` (com anexos) o limite é o das respostas mais a soma
 * máxima de arquivos (`submissions.max_total_upload_mb`). O teto absoluto
 * continua sendo o `post_max_size` do PHP.
 */
final class LimitRequestSize
{
    // Respostas em JSON: 100 campos de texto típicos cabem com folga.
    public const SUBMISSION_MAX_KILOBYTES = 1024;

    public function handle(Request $request, Closure $next, string $maxKilobytes): Response
    {
        $maxBytes = (int) $maxKilobytes * 1024;

        if (str_starts_with((string) $request->header('Content-Type'), 'multipart/form-data')) {
            $maxBytes += (int) config('submissions.max_total_upload_mb') * 1024 * 1024;
        }

        $declared = (int) $request->header('Content-Length', '0');

        // Content-Length pode faltar (chunked) ou mentir: confere também o corpo lido.
        if ($declared > $maxBytes || strlen((string) $request->getContent()) > $maxBytes) {
            throw new HttpException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }
}
