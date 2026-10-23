<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Erro de regra de negócio com mensagem escrita por nós, segura para o cliente.
 * O ApiExceptionRenderer mantém a mensagem mesmo em status genéricos (ex.: 403).
 */
final class ApiException extends HttpException
{
    public static function forbidden(string $message): self
    {
        return new self(Response::HTTP_FORBIDDEN, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(Response::HTTP_CONFLICT, $message);
    }
}
