<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;

/**
 * Base dos emails de resposta.
 *
 * HTML simples (não Markdown mail): no Markdown mail o texto escapado ainda passa
 * pelo parser de Markdown, e uma resposta como "[clique](http://golpe)" viraria um
 * link de verdade no email. Aqui todo conteúdo do respondente sai com {{ }}.
 *
 * Estas mensagens são enviadas por Jobs (fila `emails`), que cuidam de
 * retentativas e idempotência; por isso o Mailable em si não é enfileirado.
 */
abstract class SubmissionMail extends Mailable
{
    private const SUBJECT_TITLE_MAX_LENGTH = 120;

    /**
     * Título vem do dono do formulário: sem quebras de linha nem controle no assunto.
     */
    protected function subjectWith(string $prefix, string $formTitle): string
    {
        $title = Str::of((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', $formTitle))
            ->squish()
            ->limit(self::SUBJECT_TITLE_MAX_LENGTH);

        return "{$prefix}: {$title}";
    }
}
