<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Item do schema: campo que gera resposta ou bloco de conteúdo/estrutura (D32).
 */
enum ItemKind: string
{
    case Input = 'input';
    case Block = 'block';
}
