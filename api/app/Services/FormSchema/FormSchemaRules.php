<?php

declare(strict_types=1);

namespace App\Services\FormSchema;

use App\Enums\FieldType;
use App\Enums\FileCategory;
use App\Enums\RuleType;
use App\Enums\StrictnessPreset;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Monta as regras do Laravel que validam o schema enviado pelo builder.
 * As regras dependem do tipo de cada campo, então são geradas por índice.
 */
final class FormSchemaRules
{
    public const MAX_FIELDS = 100;

    public const MAX_OPTIONS = 100;

    public const MAX_RULES_PER_FIELD = 20;

    public const MAX_FILES_PER_FIELD = 10;

    // Teto da hospedagem compartilhada; o PHP também limita o upload.
    public const MAX_FILE_SIZE_MB = 10;

    public const DEFAULT_MAX_FILES = 1;

    public const DEFAULT_MAX_FILE_SIZE_MB = 5;

    // Identificadores viram chaves das respostas: curtos, previsíveis e sem acento.
    public const FIELD_ID_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    public const OPTION_VALUE_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,59}$/';

    public const CHOICE_DISPLAYS = ['radio', 'select'];

    /**
     * @return array<string, mixed>
     */
    public function rules(mixed $schema, string $prefix = 'schema'): array
    {
        $rules = [
            $prefix => ['required', 'array'],
            "{$prefix}.fields" => ['present', 'array', 'max:'.self::MAX_FIELDS],
            "{$prefix}.fields.*" => ['array'],
            "{$prefix}.fields.*.id" => ['required', 'string', 'regex:'.self::FIELD_ID_PATTERN, 'distinct'],
            "{$prefix}.fields.*.type" => ['required', Rule::enum(FieldType::class)],
            "{$prefix}.fields.*.label" => ['required', 'string', 'max:200'],
            "{$prefix}.fields.*.help_text" => ['nullable', 'string', 'max:500'],
            "{$prefix}.fields.*.required" => ['sometimes', 'boolean'],
            "{$prefix}.fields.*.rules" => ['sometimes', 'array', 'max:'.self::MAX_RULES_PER_FIELD],
            "{$prefix}.fields.*.rules.*" => ['array'],
            "{$prefix}.fields.*.rules.*.type" => ['required', Rule::enum(RuleType::class)],
            "{$prefix}.fields.*.rules.*.message" => ['nullable', 'string', 'max:200'],
        ];

        $fields = is_array($schema) && is_array($schema['fields'] ?? null) ? $schema['fields'] : [];

        foreach ($fields as $index => $field) {
            $type = is_array($field) ? FieldType::tryFrom((string) ($field['type'] ?? '')) : null;

            if ($type === null) {
                continue;
            }

            $rules = [...$rules, ...$this->fieldRules("{$prefix}.fields.{$index}", $type, $field)];
        }

        return $rules;
    }

    /**
     * Nomes legíveis para as mensagens de erro.
     *
     * @return array<string, string>
     */
    public function attributes(string $prefix = 'schema'): array
    {
        return [
            "{$prefix}.fields" => 'campos',
            "{$prefix}.fields.*.id" => 'identificador do campo',
            "{$prefix}.fields.*.type" => 'tipo do campo',
            "{$prefix}.fields.*.label" => 'rótulo do campo',
            "{$prefix}.fields.*.help_text" => 'texto de ajuda',
            "{$prefix}.fields.*.placeholder" => 'texto de exemplo',
            "{$prefix}.fields.*.strictness" => 'rigor',
            "{$prefix}.fields.*.options" => 'opções',
            "{$prefix}.fields.*.options.*.value" => 'valor da opção',
            "{$prefix}.fields.*.options.*.label" => 'rótulo da opção',
            "{$prefix}.fields.*.display" => 'exibição',
            "{$prefix}.fields.*.file" => 'configuração de arquivo',
            "{$prefix}.fields.*.file.accept" => 'tipos de arquivo aceitos',
            "{$prefix}.fields.*.file.max_files" => 'quantidade máxima de arquivos',
            "{$prefix}.fields.*.file.max_size_mb" => 'tamanho máximo do arquivo',
            "{$prefix}.fields.*.rules" => 'regras',
            "{$prefix}.fields.*.rules.*.type" => 'tipo da regra',
            "{$prefix}.fields.*.rules.*.message" => 'mensagem da regra',
        ];
    }

    /**
     * @param  array<mixed>  $field
     * @return array<string, mixed>
     */
    private function fieldRules(string $path, FieldType $type, array $field): array
    {
        $rules = [
            "{$path}.placeholder" => $type->acceptsPlaceholder() ? ['nullable', 'string', 'max:120'] : ['prohibited'],
            "{$path}.strictness" => $type->isText() ? ['nullable', Rule::enum(StrictnessPreset::class)] : ['prohibited'],
            // Por campo (e não com curinga): `distinct` com curinga compararia regras de campos diferentes.
            "{$path}.rules.*.type" => ['distinct', $this->ruleAppliesTo($type)],
            ...$this->optionRules($path, $type),
            ...$this->fileRules($path, $type),
        ];

        $fieldRules = is_array($field['rules'] ?? null) ? $field['rules'] : [];

        foreach ($fieldRules as $ruleIndex => $rule) {
            $ruleType = is_array($rule) ? RuleType::tryFrom((string) ($rule['type'] ?? '')) : null;

            foreach ($ruleType?->parameterRules() ?? [] as $parameter => $parameterRules) {
                $rules["{$path}.rules.{$ruleIndex}.{$parameter}"] = $parameterRules;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function optionRules(string $path, FieldType $type): array
    {
        if (! $type->hasOptions()) {
            return ["{$path}.options" => ['prohibited'], "{$path}.display" => ['prohibited']];
        }

        return [
            "{$path}.options" => ['required', 'array', 'min:1', 'max:'.self::MAX_OPTIONS],
            "{$path}.options.*" => ['array'],
            "{$path}.options.*.value" => ['required', 'string', 'regex:'.self::OPTION_VALUE_PATTERN, 'distinct'],
            "{$path}.options.*.label" => ['required', 'string', 'max:200'],
            "{$path}.display" => $type === FieldType::SingleChoice
                ? ['sometimes', Rule::in(self::CHOICE_DISPLAYS)]
                : ['prohibited'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function fileRules(string $path, FieldType $type): array
    {
        if ($type !== FieldType::File) {
            return ["{$path}.file" => ['prohibited']];
        }

        return [
            "{$path}.file" => ['required', 'array'],
            "{$path}.file.accept" => ['required', 'array', 'min:1'],
            "{$path}.file.accept.*" => ['required', Rule::enum(FileCategory::class), 'distinct'],
            "{$path}.file.max_files" => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_FILES_PER_FIELD],
            "{$path}.file.max_size_mb" => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_FILE_SIZE_MB],
        ];
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private function ruleAppliesTo(FieldType $field): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            $rule = is_string($value) ? RuleType::tryFrom($value) : null;

            if ($rule !== null && ! $rule->appliesTo($field)) {
                $fail("A regra \"{$rule->value}\" não pode ser usada em campos do tipo \"{$field->value}\".");
            }
        };
    }
}
