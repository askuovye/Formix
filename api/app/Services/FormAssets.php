<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FileCategory;
use App\Enums\FormAssetKind;
use App\Exceptions\ApiException;
use App\Models\Form;
use App\Models\FormAsset;
use App\Models\FormVersion;
use App\Services\FormSchema\FormTheme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Imagens de capa e logo do tema (D32).
 *
 * Diferente dos anexos de respostas (privados, links assinados), estas imagens
 * são **públicas**: aparecem na página do formulário para qualquer visitante.
 * São imutáveis e só são apagadas quando nem o rascunho nem nenhuma versão
 * publicada as referenciam.
 */
final class FormAssets
{
    public const MAX_PER_FORM = 20;

    public const MAX_SIZE_KB = 5120;

    public const ROUTE = 'assets.show';

    public const TOO_MANY_MESSAGE = 'Este formulário já tem imagens demais. Remova as que não usa mais.';

    public const WRONG_TYPE_MESSAGE = 'Envie uma imagem JPG, PNG ou WebP.';

    public const IN_USE_MESSAGE = 'Esta imagem ainda é usada pelo rascunho ou por uma versão publicada.';

    public const NOT_FOUND_MESSAGE = 'Imagem não encontrada neste formulário.';

    // Cache de 1 ano: o conteúdo de um id nunca muda.
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function __construct(private readonly FileTypeDetector $detector) {}

    public function store(Form $form, UploadedFile $file, FormAssetKind $kind): FormAsset
    {
        if ($form->assets()->count() >= self::MAX_PER_FORM) {
            throw ApiException::conflict(self::TOO_MANY_MESSAGE);
        }

        $mimeType = $this->detector->detect($file);

        if (! in_array($mimeType, FileCategory::Image->mimeTypes(), strict: true)) {
            throw ValidationException::withMessages(['file' => self::WRONG_TYPE_MESSAGE]);
        }

        $disk = (string) config('submissions.asset_disk');
        $path = $file->storeAs(
            "form-assets/{$form->id}",
            Str::uuid()->toString().'.'.FileCategory::extensionFor($mimeType),
            ['disk' => $disk],
        );

        $asset = new FormAsset;
        $asset->forceFill([
            'form_id' => $form->id,
            'kind' => $kind,
            'disk' => $disk,
            'path' => (string) $path,
            'mime_type' => $mimeType,
            'size' => (int) $file->getSize(),
            'created_at' => now(),
        ])->save();

        return $asset;
    }

    public function delete(FormAsset $asset): void
    {
        if ($this->isReferenced($asset)) {
            throw ApiException::conflict(self::IN_USE_MESSAGE);
        }

        $this->destroy($asset);
    }

    /**
     * Remove arquivo e registro sem checar referências (uso interno: limpeza de órfãs).
     */
    public function destroy(FormAsset $asset): void
    {
        Storage::disk($asset->disk)->delete($asset->path);
        $asset->delete();
    }

    /**
     * Referenciada pelo tema do rascunho ou de qualquer versão publicada.
     */
    public function isReferenced(FormAsset $asset): bool
    {
        $draftTheme = Form::withTrashed()->find($asset->form_id)?->schema['theme'] ?? null;

        if (in_array($asset->id, FormTheme::assetIds(is_array($draftTheme) ? $draftTheme : null), strict: true)) {
            return true;
        }

        // O id (ULID) é único: procurá-lo no JSON do schema é exato e portável entre bancos.
        return FormVersion::query()
            ->where('form_id', $asset->form_id)
            ->where('schema', 'like', '%"'.$asset->id.'"%')
            ->exists();
    }

    /**
     * O tema só pode apontar para imagens deste formulário, do tipo certo (capa/logo).
     *
     * @param  array<string, mixed>  $schema  schema já normalizado
     */
    public function assertThemeAssetsBelongTo(Form $form, array $schema): void
    {
        $theme = is_array($schema['theme'] ?? null) ? $schema['theme'] : [];

        foreach ([FormAssetKind::Cover, FormAssetKind::Logo] as $kind) {
            $assetId = $theme[$kind->value]['asset_id'] ?? null;

            if ($assetId === null) {
                continue;
            }

            $exists = FormAsset::query()
                ->whereKey($assetId)
                ->where('form_id', $form->id)
                ->where('kind', $kind)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages(["schema.theme.{$kind->value}.asset_id" => self::NOT_FOUND_MESSAGE]);
            }
        }
    }

    /**
     * Schema com a URL pública das imagens do tema (`theme.cover.url`, `theme.logo.url`).
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function withUrls(array $schema): array
    {
        foreach ([FormAssetKind::Cover, FormAssetKind::Logo] as $kind) {
            $assetId = $schema['theme'][$kind->value]['asset_id'] ?? null;

            if (is_string($assetId)) {
                $schema['theme'][$kind->value]['url'] = $this->url($assetId);
            }
        }

        return $schema;
    }

    public function url(string $assetId): string
    {
        return route(self::ROUTE, ['asset' => $assetId]);
    }

    public function response(FormAsset $asset): StreamedResponse
    {
        return Storage::disk($asset->disk)->response($asset->path, null, [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => self::CACHE_CONTROL,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
