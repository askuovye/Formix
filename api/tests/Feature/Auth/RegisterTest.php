<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\RegisterController;
use App\Models\User;
use App\Notifications\AccountAlreadyExistsNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Maria Souza',
        'email' => 'maria@example.com',
        'password' => 'senhaForte123',
        'password_confirmation' => 'senhaForte123',
        'terms_accepted' => true,
        ...$overrides,
    ];
}

function register(array $payload): TestResponse
{
    return test()->withHeaders(spaHeaders())->postJson('/api/v1/auth/register', $payload);
}

it('cria a conta, envia a verificação na fila de emails e responde 202 genérico', function () {
    register(registrationPayload())
        ->assertAccepted()
        ->assertExactJson(['message' => RegisterController::MESSAGE]);

    $user = User::query()->sole();

    expect($user->email)->toBe('maria@example.com')
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(Hash::check('senhaForte123', $user->password))->toBeTrue();

    Notification::assertSentTo($user, VerifyEmailNotification::class, fn ($notification) => $notification->queue === 'emails');
});

it('normaliza o email para minúsculas e sem espaços', function () {
    register(registrationPayload(['email' => '  Maria@Example.COM ']))->assertAccepted();

    expect(User::query()->sole()->email)->toBe('maria@example.com');
});

it('não revela que o email já tem conta verificada', function () {
    $existing = User::factory()->create(['email' => 'maria@example.com', 'password' => 'senhaOriginal1']);

    register(registrationPayload())
        ->assertAccepted()
        ->assertExactJson(['message' => RegisterController::MESSAGE]);

    expect(User::query()->count())->toBe(1)
        ->and(Hash::check('senhaOriginal1', $existing->fresh()->password))->toBeTrue();

    Notification::assertSentTo($existing, AccountAlreadyExistsNotification::class);
    Notification::assertNotSentTo($existing, VerifyEmailNotification::class);
});

it('reenvia a verificação quando o email existe mas não foi confirmado', function () {
    $existing = User::factory()->unverified()->create(['email' => 'maria@example.com']);

    register(registrationPayload())->assertAccepted();

    Notification::assertSentTo($existing, VerifyEmailNotification::class);
    Notification::assertNotSentTo($existing, AccountAlreadyExistsNotification::class);
});

it('valida os campos obrigatórios com mensagens em português', function () {
    register([])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password'])
        ->assertJsonPath('errors.name.0', 'O campo nome é obrigatório.');

    expect(User::query()->count())->toBe(0);
});

it('rejeita senha fraca ou sem confirmação', function (array $overrides) {
    register(registrationPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
})->with([
    'curta' => [['password' => 'abc12', 'password_confirmation' => 'abc12']],
    'sem números' => [['password' => 'somenteletras', 'password_confirmation' => 'somenteletras']],
    'sem letras' => [['password' => '12345678', 'password_confirmation' => '12345678']],
    'confirmação divergente' => [['password_confirmation' => 'outraSenha123']],
    'acima do limite do bcrypt' => [['password' => str_repeat('a1', 37), 'password_confirmation' => str_repeat('a1', 37)]],
]);

it('rejeita email inválido', function () {
    register(registrationPayload(['email' => 'nao-e-email']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('limita cadastros repetidos para o mesmo email', function () {
    for ($i = 0; $i < 3; $i++) {
        register(registrationPayload())->assertAccepted();
    }

    register(registrationPayload())->assertTooManyRequests();
});
