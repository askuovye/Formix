<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Filas da aplicação, em ordem de prioridade (decisões D5 e D16).
 */
enum QueueName: string
{
    case Emails = 'emails';
    case Default = 'default';
    case Exports = 'exports';
}
