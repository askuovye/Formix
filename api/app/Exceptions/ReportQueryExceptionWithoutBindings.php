<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * A mensagem padrão da QueryException traz o SQL com os valores preenchidos
 * (respostas, emails, hashes). Registrada como veio, um erro de banco durante
 * um envio gravaria dados pessoais no log (AGENTS.md §6, LGPD).
 *
 * Aqui o log leva só o SQL com placeholders e o código do erro; retornar `false`
 * impede o registro padrão do framework.
 */
final class ReportQueryExceptionWithoutBindings
{
    public function __invoke(QueryException $exception): false
    {
        Log::error('Erro de banco de dados.', [
            'connection' => $exception->getConnectionName(),
            'sql' => $exception->getSql(),
            'sqlstate' => $exception->getCode(),
            'driver_code' => $exception->errorInfo[1] ?? null,
        ]);

        return false;
    }
}
