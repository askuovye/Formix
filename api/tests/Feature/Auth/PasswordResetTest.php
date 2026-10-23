<?php

declare(strict_types=1);

use App\Actions\Auth\ResetUserPassword;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

function forgotPassword(string $email): TestResponse
{
    return test()->withHeaders(spaHeaders())->postJson('/api/v1/auth/forgot-password', ['email' => $email]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function resetPassword(array $overrides): TestResponse
{
    return test()->withHeaders(spaHeaders())->postJson('/api/v1/auth/reset-password', [
        'password' => 'novaSenha123',
        'password_confirmation' => 'novaSenha123',
        ...$overrides,
    ]);
}

it('envia o link de redefinição apontando para a SPA', function () {
    $user = User::factory()->create(['email' => 'maria@example.com']);

    forgotPassword('maria@example.com')
        ->assertAccepted()
        ->assertExactJson(['message' => PasswordResetController::LINK_SENT_MESSAGE]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, 'http://localhost:5173/redefinir-senha?')
            && str_contains($url, 'email=maria%40example.com')
            && $notification->queue === 'emails';
    });
});

it('responde igual para email sem conta, sem enviar nada', function () {
    forgotPassword('ninguem@example.com')
        ->assertAccepted()
        ->assertExactJson(['message' => PasswordResetController::LINK_SENT_MESSAGE]);

    Notification::assertNothingSent();
});

it('redefine a senha com token válido e troca o remember token', function () {
    $user = User::factory()->create(['email' => 'maria@example.com']);
    $originalRememberToken = $user->remember_token;
    $token = Password::broker()->createToken($user);

    resetPassword(['email' => 'maria@example.com', 'token' => $token])
        ->assertOk()
        ->assertExactJson(['message' => PasswordResetController::RESET_MESSAGE]);

    $user->refresh();
    expect(Hash::check('novaSenha123', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($originalRememberToken);
});

it('não aceita reutilizar o mesmo token', function () {
    $user = User::factory()->create(['email' => 'maria@example.com']);
    $token = Password::broker()->createToken($user);

    resetPassword(['email' => 'maria@example.com', 'token' => $token])->assertOk();
    resetPassword(['email' => 'maria@example.com', 'token' => $token])->assertUnprocessable();
});

it('responde com a mesma mensagem para token inválido e email sem conta', function (string $email) {
    User::factory()->create(['email' => 'maria@example.com']);

    resetPassword(['email' => $email, 'token' => 'token-invalido'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.token.0', ResetUserPassword::INVALID_TOKEN_MESSAGE);
})->with(['token inválido' => 'maria@example.com', 'email sem conta' => 'ninguem@example.com']);

it('rejeita token expirado', function () {
    $user = User::factory()->create(['email' => 'maria@example.com']);
    $token = Password::broker()->createToken($user);

    $this->travel(61)->minutes();

    resetPassword(['email' => 'maria@example.com', 'token' => $token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

it('aplica a política de senha na redefinição', function () {
    $user = User::factory()->create(['email' => 'maria@example.com']);

    resetPassword([
        'email' => 'maria@example.com',
        'token' => Password::broker()->createToken($user),
        'password' => 'fraca',
        'password_confirmation' => 'fraca',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
});

it('limita pedidos de redefinição para o mesmo email', function () {
    for ($i = 0; $i < 5; $i++) {
        forgotPassword('maria@example.com')->assertAccepted();
    }

    forgotPassword('maria@example.com')->assertTooManyRequests();
});
