<?php

declare(strict_types=1);

use App\Actions\CheckSystemHealth;

function useHealthChecks(CheckSystemHealth $action): void
{
    app()->instance(CheckSystemHealth::class, $action);
}

it('responde 200 com o estado de cada componente', function () {
    useHealthChecks(new CheckSystemHealth(
        fakeHealthCheck('database', passes: true),
        fakeHealthCheck('queue', passes: false, critical: false),
    ));

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['data' => [
            'status' => 'degraded',
            'checks' => ['database' => true, 'queue' => false],
        ]]);
});

it('responde 503 quando um componente crítico está fora do ar', function () {
    useHealthChecks(new CheckSystemHealth(fakeHealthCheck('database', passes: false)));

    $this->getJson('/api/v1/health')
        ->assertServiceUnavailable()
        ->assertJsonPath('data.status', 'down');
});

it('limita a frequência de chamadas por IP', function () {
    useHealthChecks(new CheckSystemHealth(fakeHealthCheck('database', passes: true)));

    for ($i = 0; $i < 30; $i++) {
        $this->getJson('/api/v1/health')->assertOk();
    }

    $this->getJson('/api/v1/health')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('mantém o endpoint /up do framework para o load balancer', function () {
    $this->get('/up')->assertOk();
});
