<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Form;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Slug legível + sufixo aleatório: o link fica amigável sem permitir
 * adivinhar formulários de outras pessoas a partir do título.
 */
final class FormSlugGenerator
{
    private const BASE_MAX_LENGTH = 60;

    private const SUFFIX_LENGTH = 6;

    private const FALLBACK_BASE = 'formulario';

    private const MAX_ATTEMPTS = 5;

    public function generate(string $title): string
    {
        $base = Str::of(Str::slug($title))->limit(self::BASE_MAX_LENGTH, '')->trim('-')->value();
        $base = $base !== '' ? $base : self::FALLBACK_BASE;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $slug = $base.'-'.Str::lower(Str::random(self::SUFFIX_LENGTH));

            // withTrashed: slug de formulário excluído continua reservado.
            if (! Form::withTrashed()->where('slug', $slug)->exists()) {
                return $slug;
            }
        }

        throw new RuntimeException('Não foi possível gerar um endereço único para o formulário.');
    }
}
