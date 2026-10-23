<?php

declare(strict_types=1);

use App\Actions\Forms\CloseForm;
use App\Actions\Forms\PublishForm;
use App\Actions\Forms\UpdateForm;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\User;
use App\Services\FormSchema\FormConsistency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function publishForm(Form $form): TestResponse
{
    return test()->actingAs($form->user)->postJson("/api/v1/me/forms/{$form->id}/publish");
}

function closeForm(Form $form): TestResponse
{
    return test()->actingAs($form->user)->postJson("/api/v1/me/forms/{$form->id}/close");
}

it('publica o rascunho criando a versão 1 com o conteúdo atual', function () {
    $form = Form::factory()->withFields()->create(['title' => 'Cadastro', 'description' => 'Descrição']);

    publishForm($form)
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.published_version', 1)
        ->assertJsonPath('data.has_unpublished_changes', false);

    $version = FormVersion::query()->sole();

    expect($version->version)->toBe(1)
        ->and($version->title)->toBe('Cadastro')
        ->and($version->description)->toBe('Descrição')
        ->and($version->schema)->toBe($form->schema)
        ->and($form->fresh()->published_at)->not->toBeNull();
});

it('exige email confirmado para publicar', function () {
    $form = Form::factory()->withFields()->for(User::factory()->unverified())->create();

    publishForm($form)
        ->assertForbidden()
        ->assertExactJson(['message' => PublishForm::EMAIL_NOT_VERIFIED_MESSAGE]);

    expect($form->fresh()->status)->toBe(FormStatus::Draft)
        ->and(FormVersion::query()->count())->toBe(0);
});

it('não publica formulário sem campos', function () {
    $form = Form::factory()->create();

    publishForm($form)
        ->assertUnprocessable()
        ->assertJsonPath('errors.schema.0', FormConsistency::NO_FIELDS_MESSAGE);
});

it('editar depois de publicar muda só o rascunho; a versão publicada fica intacta', function () {
    $form = Form::factory()->withFields()->create(['title' => 'Original']);
    publishForm($form)->assertOk();

    $this->patchJson("/api/v1/me/forms/{$form->id}", ['title' => 'Editado', 'schema' => validFormSchema()])
        ->assertOk()
        ->assertJsonPath('data.title', 'Editado')
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.published_version', 1)
        ->assertJsonPath('data.has_unpublished_changes', true);

    $version = FormVersion::query()->sole();

    expect($version->title)->toBe('Original')
        ->and($version->schema['fields'])->toHaveCount(2);
});

it('publicar de novo com alterações gera a versão 2', function () {
    $form = Form::factory()->withFields()->create();
    publishForm($form)->assertOk();
    $this->patchJson("/api/v1/me/forms/{$form->id}", ['schema' => validFormSchema()])->assertOk();

    publishForm($form)->assertOk()->assertJsonPath('data.published_version', 2);

    expect(FormVersion::query()->orderBy('version')->pluck('version')->all())->toBe([1, 2])
        ->and(FormVersion::query()->where('version', 2)->sole()->schema['fields'])->toHaveCount(5);
});

it('publicar de novo sem alterações não gera versão', function () {
    $form = Form::factory()->withFields()->create();
    publishForm($form)->assertOk();

    publishForm($form)->assertOk()->assertJsonPath('data.published_version', 1);

    expect(FormVersion::query()->count())->toBe(1);
});

it('mudar só as configurações não gera alterações não publicadas', function () {
    $form = Form::factory()->withFields()->create();
    publishForm($form)->assertOk();

    $this->patchJson("/api/v1/me/forms/{$form->id}", ['settings' => ['max_submissions' => 10]])
        ->assertOk()
        ->assertJsonPath('data.has_unpublished_changes', false)
        ->assertJsonPath('data.settings.max_submissions', 10);
});

it('fecha um formulário publicado', function () {
    $form = Form::factory()->withFields()->create();
    publishForm($form)->assertOk();

    closeForm($form)
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.closed_at', fn (?string $closedAt): bool => $closedAt !== null);
});

it('não fecha formulário que não está publicado', function (FormStatus $status) {
    $form = Form::factory()->withFields()->create(['status' => $status]);

    closeForm($form)
        ->assertConflict()
        ->assertExactJson(['message' => CloseForm::NOT_PUBLISHED_MESSAGE]);
})->with([FormStatus::Draft, FormStatus::Closed]);

it('o dono reabre um formulário fechado publicando de novo, sem nova versão', function () {
    $form = Form::factory()->withFields()->create();
    publishForm($form)->assertOk();
    closeForm($form)->assertOk();

    publishForm($form)
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.closed_at', null)
        ->assertJsonPath('data.published_version', 1);

    expect(FormVersion::query()->count())->toBe(1);
});

it('permite trocar o endereço enquanto nunca foi publicado', function () {
    $form = Form::factory()->create();

    $this->actingAs($form->user)
        ->patchJson("/api/v1/me/forms/{$form->id}", ['slug' => 'meu-evento-2026'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'meu-evento-2026');
});

it('trava o endereço depois da primeira publicação', function () {
    $form = Form::factory()->withFields()->create();
    publishForm($form)->assertOk();

    $this->patchJson("/api/v1/me/forms/{$form->id}", ['slug' => 'outro-endereco'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.slug.0', UpdateForm::SLUG_LOCKED_MESSAGE);
});

it('não aceita endereço já usado, nem por formulário excluído', function () {
    $deleted = Form::factory()->create(['slug' => 'endereco-usado']);
    $deleted->delete();
    $form = Form::factory()->create();

    $this->actingAs($form->user)
        ->patchJson("/api/v1/me/forms/{$form->id}", ['slug' => 'endereco-usado'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

it('rejeita endereço fora do formato', function (string $slug) {
    $form = Form::factory()->create();

    $this->actingAs($form->user)
        ->patchJson("/api/v1/me/forms/{$form->id}", ['slug' => $slug])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
})->with(['Maiúsculas', 'com espaço', 'acentuação', '-comeca-com-hifen', 'ab', '../admin']);

it('impede alterar uma versão publicada no código', function () {
    $form = Form::factory()->withFields()->create();
    publishForm($form)->assertOk();

    $version = FormVersion::query()->sole();
    $version->title = 'Adulterado';

    expect(fn () => $version->save())->toThrow(LogicException::class);
    expect(FormVersion::query()->sole()->title)->toBe($form->title);
});
