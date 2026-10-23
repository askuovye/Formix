<?php

declare(strict_types=1);

use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cria um rascunho com endereço gerado a partir do título', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/me/forms', ['title' => 'Inscrição no Evento!', 'description' => 'Preencha até sexta.'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Inscrição no Evento!')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.published_version', 0)
        ->assertJsonPath('data.has_unpublished_changes', true)
        ->assertJsonPath('data.schema', ['fields' => []])
        ->assertJsonPath('data.settings.max_submissions', null);

    $slug = $response->json('data.slug');

    expect($slug)->toMatch('/^inscricao-no-evento-[a-z0-9]{6}$/')
        ->and($response->json('data.public_url'))->toBe("http://localhost:5173/f/{$slug}")
        ->and(Form::query()->sole()->user_id)->toBe($user->id);
});

it('exige título', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/me/forms', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.title.0', 'O campo título é obrigatório.');
});

it('exige login em todas as rotas de formulário', function (string $method, string $uri) {
    $form = Form::factory()->create();

    $this->json($method, str_replace('{form}', $form->id, $uri))->assertUnauthorized();
})->with([
    ['GET', '/api/v1/me/forms'],
    ['POST', '/api/v1/me/forms'],
    ['GET', '/api/v1/me/forms/{form}'],
    ['PATCH', '/api/v1/me/forms/{form}'],
    ['DELETE', '/api/v1/me/forms/{form}'],
    ['POST', '/api/v1/me/forms/{form}/publish'],
    ['POST', '/api/v1/me/forms/{form}/close'],
]);

it('lista só os formulários do próprio usuário, do mais novo para o mais antigo', function () {
    $user = User::factory()->create();
    $older = Form::factory()->for($user)->create();
    $this->travel(1)->seconds();
    $newer = Form::factory()->for($user)->create();
    Form::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/me/forms')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonMissingPath('data.0.schema');
});

it('pagina a listagem por cursor', function () {
    $user = User::factory()->create();
    Form::factory()->for($user)->count(3)->create();

    $first = $this->actingAs($user)->getJson('/api/v1/me/forms?per_page=2')->assertOk()->assertJsonCount(2, 'data');
    $cursor = $first->json('meta.next_cursor');

    expect($cursor)->toBeString();

    $this->getJson("/api/v1/me/forms?per_page=2&cursor={$cursor}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.next_cursor', null);
});

it('filtra a listagem por status', function () {
    $user = User::factory()->create();
    Form::factory()->for($user)->create();
    Form::factory()->for($user)->create(['status' => FormStatus::Closed]);

    $this->actingAs($user)
        ->getJson('/api/v1/me/forms?status=closed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'closed');
});

it('limita o tamanho da página', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/me/forms?per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});

it('mostra o formulário completo ao dono', function () {
    $form = Form::factory()->withFields()->create();

    $this->actingAs($form->user)
        ->getJson("/api/v1/me/forms/{$form->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $form->id)
        ->assertJsonPath('data.schema.fields.0.id', 'nome');
});

it('responde 404 para formulário de outra pessoa, sem revelar que existe', function (string $method, string $suffix) {
    $form = Form::factory()->withFields()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->json($method, "/api/v1/me/forms/{$form->id}{$suffix}", ['title' => 'Invadido'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.']);

    expect($form->fresh()->title)->not->toBe('Invadido')
        ->and($form->fresh()->trashed())->toBeFalse()
        ->and($form->fresh()->status)->toBe(FormStatus::Draft);
})->with([
    'ver' => ['GET', ''],
    'editar' => ['PATCH', ''],
    'excluir' => ['DELETE', ''],
    'publicar' => ['POST', '/publish'],
    'fechar' => ['POST', '/close'],
]);

it('atualiza título e descrição e marca alterações não publicadas', function () {
    $form = Form::factory()->create(['has_unpublished_changes' => false]);

    $this->actingAs($form->user)
        ->patchJson("/api/v1/me/forms/{$form->id}", ['title' => 'Novo título', 'description' => null])
        ->assertOk()
        ->assertJsonPath('data.title', 'Novo título')
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.has_unpublished_changes', true);
});

it('não marca alteração quando o conteúdo enviado é igual ao atual', function () {
    $form = Form::factory()->create(['title' => 'Mesmo título', 'has_unpublished_changes' => false]);

    $this->actingAs($form->user)
        ->patchJson("/api/v1/me/forms/{$form->id}", ['title' => 'Mesmo título'])
        ->assertOk()
        ->assertJsonPath('data.has_unpublished_changes', false);
});

it('exclui de forma lógica e o formulário some das rotas', function () {
    $form = Form::factory()->create();

    $this->actingAs($form->user)->deleteJson("/api/v1/me/forms/{$form->id}")->assertNoContent();

    expect(Form::withTrashed()->find($form->id)?->trashed())->toBeTrue();

    $this->getJson("/api/v1/me/forms/{$form->id}")->assertNotFound();
    $this->getJson('/api/v1/me/forms')->assertJsonCount(0, 'data');
});
