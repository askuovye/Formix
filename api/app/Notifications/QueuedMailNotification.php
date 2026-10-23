<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\QueueName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base dos emails transacionais: sempre na fila `emails`, com retentativas espaçadas.
 */
abstract class QueuedMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct()
    {
        $this->onQueue(QueueName::Emails->value);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        // Sem dados do destinatário no log (AGENTS.md §6).
        Log::warning('Falha definitiva ao enviar notificação por email.', [
            'notification' => static::class,
            'exception' => $exception::class,
        ]);
    }
}
