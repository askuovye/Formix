<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Form;
use Carbon\CarbonInterface;

/**
 * Se o formulário aceita respostas agora, e por que não, quando for o caso.
 */
enum FormAvailability: string
{
    case Open = 'open';
    case Closed = 'closed';
    case NotOpenYet = 'not_open_yet';
    case Ended = 'ended';
    case LimitReached = 'limit_reached';

    public static function of(Form $form, ?CarbonInterface $now = null): self
    {
        $now ??= now();
        $settings = $form->settings;

        return match (true) {
            $form->status !== FormStatus::Published => self::Closed,
            $settings->opensAt !== null && $now->lt($settings->opensAt) => self::NotOpenYet,
            $settings->closesAt !== null && $now->gte($settings->closesAt) => self::Ended,
            $settings->maxSubmissions !== null && $form->submissions_count >= $settings->maxSubmissions => self::LimitReached,
            default => self::Open,
        };
    }

    public function acceptsSubmissions(): bool
    {
        return $this === self::Open;
    }

    public function message(): ?string
    {
        return match ($this) {
            self::Open => null,
            self::Closed => 'Este formulário não está aceitando respostas.',
            self::NotOpenYet => 'Este formulário ainda não está aberto para respostas.',
            self::Ended => 'O prazo para responder este formulário terminou.',
            self::LimitReached => 'Este formulário atingiu o limite de respostas.',
        };
    }
}
