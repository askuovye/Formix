<?php

declare(strict_types=1);

use App\Actions\Forms\CloseForm;
use App\Data\FormSettings;
use App\Enums\FormAvailability;
use App\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('mostra a versão publicada para quem acessa o link', function () {
    $form = publishedForm();

    $this->getJson("/api/v1/forms/{$form->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $form->slug)
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.title', $form->title)
        ->assertJsonPath('data.accepts_submissions', true)
        ->assertJsonPath('data.availability', 'open')
        ->assertJsonPath('data.unavailable_message', null)
        ->assertJsonPath('data.schema.fields.0.id', 'nome')
        ->assertJsonPath('data.privacy_policy_url', 'http://localhost:5173/privacidade');
});

it('não mostra alterações do rascunho antes de publicar de novo', function () {
    $form = publishedForm();
    $form->forceFill(['title' => 'Rascunho novo', 'schema' => ['fields' => []]])->save();

    $this->getJson("/api/v1/forms/{$form->slug}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Pesquisa de satisfação')
        ->assertJsonCount(5, 'data.schema.fields');
});

it('não expõe dados internos do formulário', function () {
    $form = publishedForm(['notification_emails' => ['interno@example.com'], 'max_submissions' => 100]);

    $response = $this->getJson("/api/v1/forms/{$form->slug}")->assertOk();

    expect(array_keys($response->json('data')))->not->toContain('id', 'user_id', 'settings', 'submissions_count', 'status')
        ->and($response->getContent())->not->toContain('interno@example.com', $form->user->email);
});

it('responde 404 para formulário inexistente, nunca publicado ou excluído', function (Closure $makeSlug) {
    $this->getJson('/api/v1/forms/'.$makeSlug())->assertNotFound();
})->with([
    'inexistente' => fn (): string => 'nao-existe',
    'rascunho nunca publicado' => fn (): string => Form::factory()->withFields()->create()->slug,
    'excluído' => function (): string {
        $form = publishedForm();
        $form->delete();

        return $form->slug;
    },
]);

it('informa quando o formulário não aceita respostas, sem entregar os campos', function (array $settings, ?Closure $after, FormAvailability $expected) {
    $form = publishedForm($settings);
    $after?->__invoke($form);

    $this->getJson("/api/v1/forms/{$form->slug}")
        ->assertOk()
        ->assertJsonPath('data.accepts_submissions', false)
        ->assertJsonPath('data.availability', $expected->value)
        ->assertJsonPath('data.unavailable_message', $expected->message())
        ->assertJsonPath('data.schema', null);
})->with([
    'fechado' => [[], fn (Form $form) => app(CloseForm::class)($form), FormAvailability::Closed],
    'ainda não abriu' => [['opens_at' => now()->addDay()->toIso8601String()], null, FormAvailability::NotOpenYet],
    'prazo encerrado' => [['closes_at' => now()->subMinute()->toIso8601String()], null, FormAvailability::Ended],
    'limite atingido' => [['max_submissions' => 1], fn (Form $form) => $form->forceFill(['submissions_count' => 1])->save(), FormAvailability::LimitReached],
]);

it('reabre automaticamente quando chega a hora de abertura', function () {
    $form = publishedForm(['opens_at' => now()->addHour()->toIso8601String()]);

    $this->getJson("/api/v1/forms/{$form->slug}")->assertJsonPath('data.accepts_submissions', false);

    $this->travel(61)->minutes();

    $this->getJson("/api/v1/forms/{$form->slug}")->assertJsonPath('data.accepts_submissions', true);
});

it('reaproveita o conteúdo da versão em cache entre acessos', function () {
    $form = publishedForm();
    $this->getJson("/api/v1/forms/{$form->slug}")->assertOk();

    DB::enableQueryLog();
    $this->getJson("/api/v1/forms/{$form->slug}")->assertOk();

    $versionQueries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'form_versions'));

    expect($versionQueries)->toBeEmpty();
});

it('aplica as configurações na hora, sem republicar', function () {
    $form = publishedForm();
    $form->forceFill(['settings' => new FormSettings(closesAt: now()->subMinute()->toImmutable())])->save();

    $this->getJson("/api/v1/forms/{$form->slug}")->assertJsonPath('data.availability', 'ended');
});
