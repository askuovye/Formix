<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Services\FrontendUrlGenerator;
use Illuminate\Notifications\Messages\MailMessage;

final class ResetPasswordNotification extends QueuedMailNotification
{
    public function __construct(#[\SensitiveParameter] private readonly string $token)
    {
        parent::__construct();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = app(FrontendUrlGenerator::class)->passwordReset($notifiable->getEmailForPasswordReset(), $this->token);
        $expiresIn = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Redefinição de senha')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Recebemos um pedido para redefinir a senha da sua conta.')
            ->action('Redefinir senha', $url)
            ->line("O link expira em {$expiresIn} minutos.")
            ->line('Se você não pediu a redefinição, ignore este email: sua senha continua a mesma.');
    }
}
