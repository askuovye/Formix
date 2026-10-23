<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida: draft → published ⇄ closed. Publicar de novo um formulário
 * publicado gera nova versão (se houver mudanças); reabrir um fechado é publicar.
 */
enum FormStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
}
