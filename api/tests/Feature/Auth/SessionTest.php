<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

uses(RefreshDatabase::class);

it('devolve o usuário autenticado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeaders(spaHeaders())
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', $user->name);
});

it('responde 401 sem sessão', function () {
    $this->withHeaders(spaHeaders())
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('encerra a sessão no logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertGuest('web');
});

it('exige sessão para fazer logout', function () {
    $this->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
});

it('invalida sessões antigas quando a senha muda (AuthenticateSession do Sanctum)', function () {
    expect(config('sanctum.middleware.authenticate_session'))->toBe(AuthenticateSession::class);
});
