<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\AnswerRule;
use App\Validation\InputNormalizer;

/**
 * Base das regras anti-lixo. Todas usam a mesma mensagem genérica: dizer
 * "tem caracteres repetidos demais" ensinaria a burlar a heurística (AGENTS.md §5.6).
 */
abstract class HeuristicRule implements AnswerRule
{
    public const MESSAGE = 'Esta resposta não parece válida. Revise e preencha com uma resposta de verdade.';

    public function __construct(protected readonly InputNormalizer $normalizer) {}

    public function message(array $params): string
    {
        return self::MESSAGE;
    }
}
