<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Padroniza as respostas de erro da API em `{ "message": ... }`.
 *
 * Mensagens de status genéricos são sempre substituídas: as originais do framework
 * vazam detalhes internos (ex.: o 404 de route model binding expõe o nome do model).
 */
final class ApiExceptionRenderer
{
    /** @var array<int, string> */
    private const GENERIC_MESSAGES = [
        Response::HTTP_UNAUTHORIZED => 'Não autenticado.',
        Response::HTTP_FORBIDDEN => 'Ação não autorizada.',
        Response::HTTP_NOT_FOUND => 'Recurso não encontrado.',
        Response::HTTP_METHOD_NOT_ALLOWED => 'Método não permitido.',
        419 => 'Sessão expirada. Recarregue a página e tente novamente.',
        Response::HTTP_REQUEST_ENTITY_TOO_LARGE => 'O conteúdo enviado é grande demais.',
        Response::HTTP_TOO_MANY_REQUESTS => 'Muitas tentativas. Aguarde um pouco e tente novamente.',
        Response::HTTP_INTERNAL_SERVER_ERROR => 'Erro interno do servidor.',
        Response::HTTP_SERVICE_UNAVAILABLE => 'Serviço temporariamente indisponível.',
    ];

    public function __invoke(Throwable $exception): ?JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return null;
        }

        if ($exception instanceof ApiException) {
            return new JsonResponse(['message' => $exception->getMessage()], $exception->getStatusCode(), $exception->getHeaders());
        }

        if ($exception instanceof AuthenticationException) {
            return $this->respond(Response::HTTP_UNAUTHORIZED);
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $this->respond(
                $exception->getStatusCode(),
                $exception->getMessage(),
                $exception->getHeaders(),
            );
        }

        // Em debug, deixa o Laravel renderizar o erro completo para facilitar o diagnóstico.
        if (config('app.debug')) {
            return null;
        }

        return $this->respond(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function respond(int $status, string $message = '', array $headers = []): JsonResponse
    {
        $message = self::GENERIC_MESSAGES[$status] ?? ($message !== '' ? $message : 'Erro na requisição.');

        return new JsonResponse(['message' => $message], $status, $headers);
    }
}
