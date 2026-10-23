<?php

declare(strict_types=1);

use App\Jobs\ApplyRetentionPolicy;
use App\Jobs\PurgeExpiredExports;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Processamento das filas por cron (decisão D16)
|--------------------------------------------------------------------------
|
| A hospedagem compartilhada não mantém processos contínuos. O cron do
| servidor chama `php artisan schedule:run` a cada minuto, e cada execução
| esvazia a fila e sai antes do próximo ciclo. `withoutOverlapping` evita
| dois workers na mesma fila; a ordem em --queue define a prioridade.
|
*/

$queueRunSeconds = 55;

Schedule::command('queue:work database --queue=emails,default --stop-when-empty --max-time='.$queueRunSeconds)
    ->name('queue-worker')
    ->everyMinute()
    ->withoutOverlapping(expiresAt: 10)
    ->runInBackground();

Schedule::command('queue:work database-long --queue=exports --stop-when-empty --max-time='.$queueRunSeconds)
    ->name('queue-worker-exports')
    ->everyMinute()
    ->withoutOverlapping(expiresAt: 20)
    ->runInBackground();

/*
| Manutenção (os Jobs entram na fila e são processados pelos workers acima).
*/

// Política de retenção por formulário (LGPD): de madrugada, horário de Brasília.
Schedule::job(new ApplyRetentionPolicy)
    ->name('retention-policy')
    ->dailyAt('03:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

// CSVs de exportação têm dados pessoais: apagados quando vencem.
Schedule::job(new PurgeExpiredExports)
    ->name('purge-expired-exports')
    ->hourly()
    ->withoutOverlapping();
