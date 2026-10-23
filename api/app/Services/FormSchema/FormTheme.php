<?php

declare(strict_types=1);

namespace App\Services\FormSchema;

/**
 * Tema do formulário (D32): versionado dentro do schema, aplicado pelo renderer.
 * Aqui ficam os valores aceitos e o padrão; o front traduz em CSS custom properties.
 */
final class FormTheme
{
    public const PRESETS = ['classic', 'minimal', 'dark', 'modern', 'custom'];

    public const RADII = ['none', 'small', 'medium', 'large'];

    // Só fontes locais ou do sistema (sem CDN).
    public const FONTS = ['inter', 'system', 'serif'];

    public const BUTTON_STYLES = ['solid', 'outline'];

    public const ALIGNMENTS = ['left', 'center'];

    public const COVER_FITS = ['cover', 'contain'];

    public const COLOR_KEYS = ['primary', 'background', 'surface', 'text'];

    public const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    public const MAX_OVERLAY_OPACITY = 80;

    /** Tema "Clássico": usado quando o schema não traz tema. */
    public const DEFAULTS = [
        'preset' => 'classic',
        'colors' => ['primary' => '#111827', 'background' => '#F6F7F9', 'surface' => '#FFFFFF', 'text' => '#111827'],
        'radius' => 'medium',
        'font' => 'inter',
        'button_style' => 'solid',
        'alignment' => 'left',
        'cover' => null,
        'logo' => null,
    ];

    /**
     * IDs de imagens referenciadas pelo tema (capa e logo).
     *
     * @param  array<string, mixed>|null  $theme
     * @return list<string>
     */
    public static function assetIds(?array $theme): array
    {
        $ids = [];

        foreach (['cover', 'logo'] as $slot) {
            $assetId = $theme[$slot]['asset_id'] ?? null;

            if (is_string($assetId) && $assetId !== '') {
                $ids[] = $assetId;
            }
        }

        return $ids;
    }
}
