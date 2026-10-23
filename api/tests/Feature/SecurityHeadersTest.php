<?php

declare(strict_types=1);

use App\Actions\CheckSystemHealth;

beforeEach(function () {
    app()->instance(CheckSystemHealth::class, new CheckSystemHealth(fakeHealthCheck('database', passes: true)));
});

it('envia os cabeçalhos de segurança nas respostas da API', function () {
    $this->getJson('/api/v1/health')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('envia HSTS apenas em conexões HTTPS', function () {
    $this->getJson('https://localhost/api/v1/health')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('envia os cabeçalhos também nas respostas de erro', function () {
    $this->getJson('/api/v1/rota-inexistente')
        ->assertNotFound()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
