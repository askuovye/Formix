<?php

declare(strict_types=1);

use App\Enums\LegalDocument;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Services\IpHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

/**
 * @param  array<string, mixed>  $overrides
 */
function registerWithTerms(array $overrides = []): TestResponse
{
    return test()
        ->withHeaders(spaHeaders())
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->postJson('/api/v1/auth/register', [
            'name' => 'Maria Souza',
            'email' => 'maria@example.com',
            'password' => 'senhaForte123',
            'password_confirmation' => 'senhaForte123',
            'terms_accepted' => true,
            ...$overrides,
        ]);
}

it('exige o aceite dos termos e da política de privacidade', function (mixed $accepted) {
    registerWithTerms(['terms_accepted' => $accepted])
        ->assertUnprocessable()
        ->assertJsonPath('errors.terms_accepted.0', 'Você precisa aceitar os Termos de Uso e a Política de Privacidade.');

    expect(User::query()->count())->toBe(0);
})->with(['ausente' => null, 'falso' => false, 'texto qualquer' => 'talvez']);

it('registra o aceite da versão vigente de cada documento', function () {
    config(['legal.documents.terms.version' => '2.1']);

    registerWithTerms()->assertAccepted();

    $acceptances = User::query()->sole()->legalAcceptances;

    expect($acceptances)->toHaveCount(2)
        ->and($acceptances->pluck('document')->all())->toEqualCanonicalizing(LegalDocument::cases())
        ->and($acceptances->firstWhere('document', LegalDocument::Terms)?->version)->toBe('2.1')
        ->and($acceptances->firstWhere('document', LegalDocument::Privacy)?->version)->toBe('1.0');
});

it('guarda só o hash do IP, nunca o IP puro', function () {
    registerWithTerms()->assertAccepted();

    $acceptance = LegalAcceptance::query()->firstOrFail();

    expect($acceptance->ip_hash)->toBe(app(IpHasher::class)->hash('203.0.113.10'))
        ->and(json_encode($acceptance->getAttributes()))->not->toContain('203.0.113.10');
});

it('não cria aceite quando o email já tem conta', function () {
    User::factory()->create(['email' => 'maria@example.com']);

    registerWithTerms()->assertAccepted();

    expect(LegalAcceptance::query()->count())->toBe(0);
});

it('remove os aceites junto com a conta', function () {
    registerWithTerms()->assertAccepted();

    User::query()->sole()->delete();

    expect(LegalAcceptance::query()->count())->toBe(0);
});
