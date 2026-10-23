<?php

declare(strict_types=1);

namespace App\Services\FormSchema;

use App\Enums\FieldType;
use App\Enums\RuleType;
use App\Enums\StrictnessPreset;

/**
 * Reconstrói o schema (já validado) só com as chaves conhecidas de cada tipo.
 *
 * Por quê: o schema vem do cliente e é guardado como JSON. Copiar campo a campo
 * descarta chaves desconhecidas e aninhamentos arbitrários, garante a mesma
 * forma para todo consumidor (motor de validação, renderer, exportação) e
 * expande os presets de rigor em regras explícitas.
 */
final class FormSchemaNormalizer
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array{fields: list<array<string, mixed>>}
     */
    public function normalize(array $schema): array
    {
        $fields = is_array($schema['fields'] ?? null) ? array_values($schema['fields']) : [];

        return ['fields' => array_map($this->field(...), $fields)];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function field(array $field): array
    {
        $type = FieldType::from((string) $field['type']);

        $normalized = [
            'id' => (string) $field['id'],
            'type' => $type->value,
            'label' => trim((string) $field['label']),
            'help_text' => $this->nullableText($field['help_text'] ?? null),
            'required' => (bool) ($field['required'] ?? false),
        ];

        if ($type->acceptsPlaceholder()) {
            $normalized['placeholder'] = $this->nullableText($field['placeholder'] ?? null);
        }

        if ($type->hasOptions()) {
            $normalized['options'] = $this->options($field['options']);
        }

        if ($type === FieldType::SingleChoice) {
            $normalized['display'] = (string) ($field['display'] ?? FormSchemaRules::CHOICE_DISPLAYS[0]);
        }

        if ($type === FieldType::File) {
            $normalized['file'] = $this->fileSettings($field['file']);
        }

        $normalized['rules'] = $this->rules($type, $field);

        return $normalized;
    }

    /**
     * @param  array<mixed>  $options
     * @return list<array{value: string, label: string}>
     */
    private function options(array $options): array
    {
        return array_values(array_map(
            fn (array $option): array => ['value' => (string) $option['value'], 'label' => trim((string) $option['label'])],
            $options,
        ));
    }

    /**
     * @param  array<string, mixed>  $file
     * @return array{accept: list<string>, max_files: int, max_size_mb: int}
     */
    private function fileSettings(array $file): array
    {
        return [
            'accept' => array_values(array_map('strval', $file['accept'])),
            'max_files' => (int) ($file['max_files'] ?? FormSchemaRules::DEFAULT_MAX_FILES),
            'max_size_mb' => (int) ($file['max_size_mb'] ?? FormSchemaRules::DEFAULT_MAX_FILE_SIZE_MB),
        ];
    }

    /**
     * Regras explícitas do campo + as do preset que ainda não estão presentes.
     * Uma regra definida pelo dono prevalece sobre a do preset (ex.: threshold próprio).
     *
     * @param  array<string, mixed>  $field
     * @return list<array<string, mixed>>
     */
    private function rules(FieldType $type, array $field): array
    {
        $rules = array_map($this->rule(...), is_array($field['rules'] ?? null) ? array_values($field['rules']) : []);

        $preset = StrictnessPreset::tryFrom((string) ($field['strictness'] ?? ''));

        if ($preset === null) {
            return $rules;
        }

        $presentTypes = array_column($rules, 'type');

        foreach ($preset->rulesFor($type) as $presetRule) {
            if (! in_array($presetRule['type'], $presentTypes, strict: true)) {
                $rules[] = $this->rule($presetRule);
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function rule(array $rule): array
    {
        $type = RuleType::from((string) $rule['type']);
        $normalized = ['type' => $type->value];

        foreach ($type->parameterKeys() as $key) {
            if (array_key_exists($key, $rule)) {
                $normalized[$key] = $this->parameter($key, $rule[$key]);
            }
        }

        $message = $this->nullableText($rule['message'] ?? null);

        if ($message !== null) {
            $normalized['message'] = $message;
        }

        return $normalized;
    }

    private function parameter(string $key, mixed $value): mixed
    {
        return match ($key) {
            'value' => (int) $value,
            'threshold' => (float) $value,
            'values' => array_values(array_map(fn (mixed $item): string => trim((string) $item), (array) $value)),
            default => $value,
        };
    }

    private function nullableText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
