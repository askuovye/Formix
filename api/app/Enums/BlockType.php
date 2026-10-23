<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Blocos do trilho "Elementos": não têm regras, não são obrigatórios e não geram valor.
 */
enum BlockType: string
{
    case SectionTitle = 'section_title';
    case Text = 'text';
    case Divider = 'divider';
    // Divide a lista plana em etapas; o renderer deriva as etapas dela.
    case PageBreak = 'page_break';

    public const SECTION_TITLE_MAX_LENGTH = 200;

    public const TEXT_MAX_LENGTH = 2000;

    public function hasText(): bool
    {
        return $this === self::SectionTitle || $this === self::Text;
    }

    public function textMaxLength(): int
    {
        return $this === self::SectionTitle ? self::SECTION_TITLE_MAX_LENGTH : self::TEXT_MAX_LENGTH;
    }
}
