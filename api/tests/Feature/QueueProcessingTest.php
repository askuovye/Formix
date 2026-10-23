<?php

declare(strict_types=1);

use App\Services\Health\QueueBacklogHealthCheck;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function pendingJob(int $secondsAgo): void
{
    DB::table('jobs')->insert([
        'queue' => 'emails',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subSeconds($secondsAgo)->getTimestamp(),
        'created_at' => now()->subSeconds($secondsAgo)->getTimestamp(),
    ]);
}

it('agenda os workers da fila a cada minuto, sem sobreposição', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'queue:work'));

    expect($events)->toHaveCount(2)
        ->and($events->every(fn (Event $event): bool => $event->expression === '* * * * *' && $event->withoutOverlapping))
        ->toBeTrue();
});

it('considera a fila saudável quando não há jobs atrasados', function () {
    pendingJob(secondsAgo: 30);

    expect((new QueueBacklogHealthCheck)->passes())->toBeTrue();
});

it('aponta problema quando há job pendente há mais de 10 minutos', function () {
    pendingJob(secondsAgo: 601);

    expect((new QueueBacklogHealthCheck)->passes())->toBeFalse();
});
