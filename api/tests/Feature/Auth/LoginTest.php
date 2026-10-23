<?php

declare(strict_types=1);

use App\Actions\Auth\AuthenticateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function login(string $email, string $password): TestResponse
{
    return test()->withHeaders(spaHeaders())->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ]);
}

it('autentica e devolve o usuário', function () {
    $user = User::factory()->create(['email' => 'maria@example.com', 'password' => 'senhaForte123']);

    login('maria@example.com', 'senhaForte123')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'maria@example.com')
        ->assertJsonPath('data.email_verified', true)
        ->assertJsonMissingPath('data.password');

    $this->assertAuthenticatedAs($user, 'web');
});

it('aceita o email com outra capitalização', function () {
    User::factory()->create(['email' => 'maria@example.com', 'password' => 'senhaForte123']);

    login('MARIA@example.com', 'senhaForte123')->assertOk();
});

it('responde com a mesma mensagem para senha errada e email inexistente', function (string $email, string $password) {
    User::factory()->create(['email' => 'maria@example.com', 'password' => 'senhaForte123']);

    login($email, $password)
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', AuthenticateUser::FAILED_MESSAGE);

    $this->assertGuest('web');
})->with([
    'senha errada' => ['maria@example.com', 'senhaErrada123'],
    'email inexistente' => ['ninguem@example.com', 'senhaForte123'],
]);

it('bloqueia tentativas em excesso', function () {
    User::factory()->create(['email' => 'maria@example.com', 'password' => 'senhaForte123']);

    for ($i = 0; $i < 5; $i++) {
        login('maria@example.com', 'senhaErrada123')->assertUnprocessable();
    }

    login('maria@example.com', 'senhaForte123')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('valida os campos obrigatórios', function () {
    $this->withHeaders(spaHeaders())->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});
