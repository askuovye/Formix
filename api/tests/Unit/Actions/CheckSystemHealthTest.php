<?php

declare(strict_types=1);

use App\Actions\CheckSystemHealth;
use App\Enums\HealthStatus;

it('fica ok quando todos os checks passam', function () {
    $report = (new CheckSystemHealth(
        fakeHealthCheck('database', passes: true),
        fakeHealthCheck('queue', passes: true, critical: false),
    ))();

    expect($report->status)->toBe(HealthStatus::Ok)
        ->and($report->checks)->toBe(['database' => true, 'queue' => true]);
});

it('fica degradado quando só um check não crítico falha', function () {
    $report = (new CheckSystemHealth(
        fakeHealthCheck('database', passes: true),
        fakeHealthCheck('queue', passes: false, critical: false),
    ))();

    expect($report->status)->toBe(HealthStatus::Degraded);
});

it('fica fora do ar quando um check crítico falha, independente da ordem', function (array $checks) {
    expect((new CheckSystemHealth(...$checks))()->status)->toBe(HealthStatus::Down);
})->with([
    'crítico antes' => [[
        fakeHealthCheck('database', passes: false),
        fakeHealthCheck('queue', passes: false, critical: false),
    ]],
    'crítico depois' => [[
        fakeHealthCheck('queue', passes: false, critical: false),
        fakeHealthCheck('database', passes: false),
    ]],
]);

it('executa todos os checks mesmo depois de uma falha crítica', function () {
    $report = (new CheckSystemHealth(
        fakeHealthCheck('database', passes: false),
        fakeHealthCheck('redis', passes: true),
    ))();

    expect($report->checks)->toBe(['database' => false, 'redis' => true]);
});
