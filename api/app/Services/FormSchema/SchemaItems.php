<?php

declare(strict_types=1);

namespace App\Services\FormSchema;

use App\Enums\ItemKind;

/**
 * Leitura do schema ciente de `kind`: só campos (`input`) geram resposta.
 * Itens sem `kind` são campos (schemas anteriores a D32).
 */
final class SchemaItems
{
    /**
     * @param  array<string, mixed>  $item
     */
    public static function isInput(array $item): bool
    {
        return ($item['kind'] ?? ItemKind::Input->value) === ItemKind::Input->value;
    }

    /**
     * @param  array{fields: list<array<string, mixed>>}  $schema
     * @return list<array<string, mixed>>
     */
    public static function inputs(array $schema): array
    {
        return array_values(array_filter($schema['fields'], self::isInput(...)));
    }
}
