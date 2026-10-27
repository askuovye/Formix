<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use App\Enums\FieldType;
use App\Services\FormSchema\SchemaItems;
use Illuminate\Support\Str;

/**
 * Email que identifica quem respondeu: a resposta do primeiro campo do tipo
 * email, em minúsculas. Guardado em `submissions.respondent_email` para filtrar
 * "todas as respostas desta pessoa" sem consultar dentro do JSON (AGENTS.md §6).
 */
final class RespondentEmail
{
    /**
     * @param  array{fields: list<array<string, mixed>>}  $schema
     * @param  array<string, mixed>  $answers
     */
    public static function from(array $schema, array $answers): ?string
    {
        foreach (SchemaItems::inputs($schema) as $field) {
            if (($field['type'] ?? null) !== FieldType::Email->value) {
                continue;
            }

            $value = $answers[(string) $field['id']] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return self::normalize($value);
            }
        }

        return null;
    }

    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
