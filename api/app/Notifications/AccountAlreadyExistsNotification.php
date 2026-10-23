<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Services\FrontendUrlGenerator;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Enviada quando alguém tenta se cadastrar com um email já verificado.
 * A API responde igual ao cadastro novo (sem revelar que a conta existe);
 * só o dono do email descobre, por aqui.
 */
final class AccountAlreadyExistsNotification extends QueuedMailNotification
{
    public function toMail(User $notifiable): MailMessage
    {
        $urls = app(FrontendUrlGenerator::class);

        return (new MailMessage)
            ->subject('Tentativa de cadastro com seu email')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Alguém tentou criar uma conta com este email, mas você já tem cadastro.')
            ->action('Entrar na minha conta', $urls->login())
            ->line("Esqueceu a senha? Redefina em: {$urls->forgotPassword()}")
            ->line('Se não foi você, nenhuma ação é necessária.');
    }
}
