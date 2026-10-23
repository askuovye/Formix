<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Services\FrontendUrlGenerator;
use Illuminate\Notifications\Messages\MailMessage;

final class VerifyEmailNotification extends QueuedMailNotification
{
    public function toMail(User $notifiable): MailMessage
    {
        $urls = app(FrontendUrlGenerator::class);

        return (new MailMessage)
            ->subject('Confirme seu email')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Confirme seu endereço de email para publicar formulários.')
            ->action('Confirmar email', $urls->emailVerification($notifiable))
            ->line("O link expira em {$urls->verificationExpiresInMinutes()} minutos.")
            ->line('Se você não criou uma conta, ignore este email.');
    }
}
