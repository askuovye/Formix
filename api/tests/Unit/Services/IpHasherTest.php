<?php

declare(strict_types=1);

use App\Services\IpHasher;

it('gera o mesmo hash para o mesmo IP, sem conter o IP', function () {
    $hasher = new IpHasher;

    $hash = $hasher->hash('203.0.113.10');

    expect($hash)->toBe($hasher->hash('203.0.113.10'))
        ->and($hash)->toHaveLength(64)
        ->and($hash)->not->toContain('203.0.113.10')
        ->and($hash)->not->toBe($hasher->hash('203.0.113.11'));
});

it('depende do sal secreto (sem ele não dá para recalcular)', function () {
    config(['app.ip_hash_salt' => 'sal-a']);
    $withSaltA = (new IpHasher)->hash('203.0.113.10');

    config(['app.ip_hash_salt' => 'sal-b']);

    expect((new IpHasher)->hash('203.0.113.10'))->not->toBe($withSaltA)
        ->and($withSaltA)->not->toBe(hash('sha256', '203.0.113.10'));
});

it('usa uma chave derivada da APP_KEY quando o sal não foi configurado', function () {
    config(['app.ip_hash_salt' => '']);

    expect((new IpHasher)->hash('203.0.113.10'))->toHaveLength(64);
});

it('devolve nulo sem IP', function (?string $ip) {
    expect((new IpHasher)->hash($ip))->toBeNull();
})->with(['nulo' => null, 'vazio' => '']);
