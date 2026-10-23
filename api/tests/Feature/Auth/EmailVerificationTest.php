<?php

declare(strict_types=1);

use App\Actions\Auth\VerifyUserEmail;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\FrontendUrlGenerator;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/**
 * Faz o que a SPA faz: lê os parâmetros do link do email e chama a API.
 */
function apiVerificationPathFromEmailLink(string $frontendLink): string
{
    parse_str((string) parse_url($frontendLink, PHP_URL_QUERY), $query);

    return "/api/v1/auth/email/verify/{$query['id']}/{$query['hash']}?".http_build_query([
        'expires' => $query['expires'],
        'signature' => $query['signature'],
    ]);
}

it('gera o link do email apontando para a SPA', function () {
    $user = User::factory()->unverified()->create();

    $link = app(FrontendUrlGenerator::class)->emailVerification($user);

    expect($link)->toStartWith('http://localhost:5173/verificar-email?')
        ->and($link)->toContain('id='.$user->id, 'hash=', 'expires=', 'signature=');
});

it('confirma o email com o link recebido, sem exigir login', function () {
    Event::fake([Verified::class]);
    $user = User::factory()->unverified()->create();
    $link = app(FrontendUrlGenerator::class)->emailVerification($user);

    $this->getJson(apiVerificationPathFromEmailLink($link))
        ->assertOk()
        ->assertExactJson(['message' => EmailVerificationController::VERIFIED_MESSAGE]);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

it('responde ok sem reemitir evento para email já confirmado', function () {
    Event::fake([Verified::class]);
    $user = User::factory()->create();
    $link = app(FrontendUrlGenerator::class)->emailVerification($user);

    $this->getJson(apiVerificationPathFromEmailLink($link))->assertOk();

    Event::assertNotDispatched(Verified::class);
});

it('rejeita link com assinatura adulterada', function () {
    $user = User::factory()->unverified()->create();
    $path = apiVerificationPathFromEmailLink(app(FrontendUrlGenerator::class)->emailVerification($user));

    $this->getJson(preg_replace('/signature=\w+/', 'signature=adulterada', $path))
        ->assertUnprocessable()
        ->assertJsonPath('errors.signature.0', VerifyUserEmail::INVALID_LINK_MESSAGE);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejeita link expirado', function () {
    $user = User::factory()->unverified()->create();
    $path = apiVerificationPathFromEmailLink(app(FrontendUrlGenerator::class)->emailVerification($user));

    $this->travel(61)->minutes();

    $this->getJson($path)->assertUnprocessable()->assertJsonValidationErrors(['signature']);
});

it('rejeita link assinado com hash de outro email', function () {
    $user = User::factory()->unverified()->create();
    $path = URL::temporarySignedRoute(
        'auth.verification.verify',
        now()->addHour(),
        ['id' => $user->id, 'hash' => sha1('outro@example.com')],
        absolute: false,
    );

    $this->getJson($path)->assertUnprocessable()->assertJsonValidationErrors(['signature']);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('reenvia a verificação para usuário não confirmado', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertAccepted()
        ->assertExactJson(['message' => EmailVerificationController::RESENT_MESSAGE]);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('não reenvia para quem já confirmou', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertAccepted();

    Notification::assertNothingSent();
});

it('exige login para reenviar a verificação', function () {
    $this->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertUnauthorized();
});
