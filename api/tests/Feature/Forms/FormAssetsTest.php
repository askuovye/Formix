<?php

declare(strict_types=1);

use App\Actions\Forms\PublishForm;
use App\Jobs\PurgeOrphanFormAssets;
use App\Models\Form;
use App\Models\FormAsset;
use App\Models\User;
use App\Services\FormAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

function pngImage(string $name = 'capa.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
}

function uploadAsset(Form $form, UploadedFile $file, string $kind = 'cover'): TestResponse
{
    return test()->actingAs($form->user)->post("/api/v1/me/forms/{$form->id}/assets", ['file' => $file, 'kind' => $kind], ['Accept' => 'application/json']);
}

function useAsCover(Form $form, string $assetId): TestResponse
{
    return test()->actingAs($form->user)->patchJson("/api/v1/me/forms/{$form->id}", ['schema' => [
        'fields' => [['id' => 'nome', 'type' => 'short_text', 'label' => 'Nome']],
        'theme' => ['cover' => ['asset_id' => $assetId, 'fit' => 'cover', 'overlay_opacity' => 30]],
    ]]);
}

it('recebe a imagem e devolve a URL pública', function () {
    $form = Form::factory()->create();

    $response = uploadAsset($form, pngImage())
        ->assertCreated()
        ->assertJsonPath('data.kind', 'cover')
        ->assertJsonPath('data.mime_type', 'image/png');

    $asset = FormAsset::query()->sole();

    expect($response->json('data.url'))->toEndWith("/api/v1/assets/{$asset->id}")
        ->and($asset->path)->toMatch("#^form-assets/{$form->id}/[0-9a-f-]{36}\\.png$#");
    Storage::disk('local')->assertExists($asset->path);
});

it('recusa o que não é imagem pelo conteúdo, não pela extensão', function () {
    $form = Form::factory()->create();

    uploadAsset($form, UploadedFile::fake()->createWithContent('capa.png', '<svg xmlns="http://www.w3.org/2000/svg"/>'))
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', FormAssets::WRONG_TYPE_MESSAGE);

    expect(FormAsset::query()->count())->toBe(0);
});

it('limita o número de imagens por formulário', function () {
    $form = Form::factory()->create();

    foreach (range(1, FormAssets::MAX_PER_FORM) as $i) {
        uploadAsset($form, pngImage("capa{$i}.png"))->assertCreated();
    }

    uploadAsset($form, pngImage())->assertConflict();
});

it('só o dono envia imagens para o formulário', function () {
    $form = Form::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post("/api/v1/me/forms/{$form->id}/assets", ['file' => pngImage(), 'kind' => 'logo'], ['Accept' => 'application/json'])
        ->assertNotFound();
});

it('serve a imagem publicamente, com cache imutável', function () {
    $form = Form::factory()->create();
    $url = uploadAsset($form, pngImage())->json('data.url');

    // Visitante sem login.
    $this->app['auth']->forgetGuards();
    $response = $this->get($url)->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('deixa de servir a imagem de formulário excluído', function () {
    $form = Form::factory()->create();
    $url = uploadAsset($form, pngImage())->json('data.url');
    $form->delete();

    $this->get($url)->assertNotFound();
});

it('aponta o tema para a imagem e devolve a URL no schema', function () {
    $form = Form::factory()->create();
    $assetId = uploadAsset($form, pngImage())->json('data.id');

    useAsCover($form, $assetId)
        ->assertOk()
        ->assertJsonPath('data.schema.theme.cover.asset_id', $assetId)
        ->assertJsonPath('data.schema.theme.cover.url', fn (string $url): bool => str_ends_with($url, "/assets/{$assetId}"));
});

it('não deixa o tema usar imagem de outro formulário', function () {
    $other = Form::factory()->create();
    $foreignId = uploadAsset($other, pngImage())->json('data.id');
    $form = Form::factory()->create();

    useAsCover($form, $foreignId)
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn (array $errors): bool => $errors['schema.theme.cover.asset_id'][0] === FormAssets::NOT_FOUND_MESSAGE);
});

it('não deixa usar um logo como capa', function () {
    $form = Form::factory()->create();
    $logoId = uploadAsset($form, pngImage(), 'logo')->json('data.id');

    useAsCover($form, $logoId)->assertUnprocessable();
});

it('não apaga imagem usada pelo rascunho', function () {
    $form = Form::factory()->create();
    $assetId = uploadAsset($form, pngImage())->json('data.id');
    useAsCover($form, $assetId)->assertOk();

    $this->deleteJson("/api/v1/me/forms/{$form->id}/assets/{$assetId}")
        ->assertConflict()
        ->assertExactJson(['message' => FormAssets::IN_USE_MESSAGE]);
});

it('mantém a imagem da versão publicada mesmo depois de trocar no rascunho', function () {
    $form = Form::factory()->create();
    $oldId = uploadAsset($form, pngImage('antiga.png'))->json('data.id');
    useAsCover($form, $oldId)->assertOk();
    app(PublishForm::class)($form->fresh(), $form->user);

    $newId = uploadAsset($form, pngImage('nova.png'))->json('data.id');
    useAsCover($form, $newId)->assertOk();

    // O público continua vendo a antiga, e ela não pode ser apagada.
    $this->getJson("/api/v1/forms/{$form->slug}")->assertJsonPath('data.schema.theme.cover.asset_id', $oldId);
    $this->actingAs($form->user)->deleteJson("/api/v1/me/forms/{$form->id}/assets/{$oldId}")->assertConflict();
});

it('apaga imagem que nada referencia', function () {
    $form = Form::factory()->create();
    $asset = FormAsset::query()->findOrFail(uploadAsset($form, pngImage())->json('data.id'));

    $this->deleteJson("/api/v1/me/forms/{$form->id}/assets/{$asset->id}")->assertNoContent();

    expect(FormAsset::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($asset->path);
});

it('não apaga imagem de outro formulário pela rota deste', function () {
    $form = Form::factory()->create();
    $other = Form::factory()->for($form->user)->create();
    $assetId = uploadAsset($other, pngImage())->json('data.id');

    $this->actingAs($form->user)->deleteJson("/api/v1/me/forms/{$form->id}/assets/{$assetId}")->assertNotFound();

    expect(FormAsset::query()->count())->toBe(1);
});

it('remove imagens órfãs depois de 24 horas, preservando as usadas', function () {
    $form = Form::factory()->create();
    $usedId = uploadAsset($form, pngImage('usada.png'))->json('data.id');
    $orphan = FormAsset::query()->findOrFail(uploadAsset($form, pngImage('orfa.png'))->json('data.id'));
    useAsCover($form, $usedId)->assertOk();

    $this->travel(PurgeOrphanFormAssets::ORPHAN_AFTER_HOURS + 1)->hours();
    (new PurgeOrphanFormAssets)->handle(app(FormAssets::class));

    expect(FormAsset::query()->pluck('id')->all())->toBe([$usedId]);
    Storage::disk('local')->assertMissing($orphan->path);
});

it('imagens são imutáveis', function () {
    $form = Form::factory()->create();
    $asset = FormAsset::query()->findOrFail(uploadAsset($form, pngImage())->json('data.id'));

    expect(fn () => $asset->forceFill(['path' => 'outro.png'])->save())->toThrow(LogicException::class);
});
