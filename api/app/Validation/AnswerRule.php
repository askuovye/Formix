<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Uma regra do catálogo (AGENTS.md §5) aplicada a uma resposta de texto.
 *
 * Recebe o valor já normalizado e não vazio (vazio é tratado por `required`).
 * Implementações devem ser lineares no tamanho da entrada e nunca usar regex
 * vinda do schema (proteção contra ReDoS).
 */
interface AnswerRule
{
    /**
     * @param  array<string, mixed>  $params  parâmetros da regra no schema (já validados)
     */
    public function passes(string $value, array $params): bool;

    /**
     * Mensagem padrão em português, usada quando o dono não definiu uma.
     *
     * @param  array<string, mixed>  $params
     */
    public function message(array $params): string;
}
