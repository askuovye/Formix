<?php

declare(strict_types=1);

namespace App\Services\FormSchema;

use App\Enums\BlockType;
use App\Enums\FieldType;
use App\Enums\FileCategory;
use App\Enums\ItemKind;
use App\Enums\RuleType;
use App\Enums\StrictnessPreset;
use App\Enums\VisibilityOperator;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Monta as regras do Laravel que validam o schema enviado pelo builder.
 * As regras dependem do `kind` e do tipo de cada item, então são geradas por índice.
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

    public const MAX_VISIBILITY_RULES = 50;

    public const RATING_MIN_STARS = 3;

    public const RATING_MAX_STARS = 10;

    public const DEFAULT_RATING_STARS = 5;

    public const SCALE_LOWEST = 0;

    public const SCALE_HIGHEST = 10;

    public const SCALE_LABEL_MAX_LENGTH = 40;

    public const CONDITION_TEXT_MAX_LENGTH = 200;

    // Identificadores viram chaves das respostas: curtos, previsíveis e sem acento.
    public const FIELD_ID_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    public const OPTION_VALUE_PATTERN = '/^[a-z0-9][a-z0-9_-]{0,59}$/';

    public const CHOICE_DISPLAYS = ['radio', 'select'];

    public const VISIBILITY_ACTIONS = ['show', 'hide'];

    /**
     * Chaves que só existem em campos (`input`); proibidas em blocos.
     */
    private const INPUT_ONLY_KEYS = ['label', 'help_text', 'required', 'placeholder', 'strictness', 'rules', 'options', 'display', 'file', 'rating', 'scale', 'visibility'];

    /**
     * @return array<string, mixed>
     */
    public function rules(mixed $schema, string $prefix = 'schema'): array
    {
        $fields = is_array($schema) && is_array($schema['fields'] ?? null) ? array_values($schema['fields']) : [];

        $rules = [
            $prefix => ['required', 'array'],
            "{$prefix}.fields" => ['present', 'array', 'max:'.self::MAX_FIELDS, $this->visibilityLimit()],
            "{$prefix}.fields.*" => ['array'],
            "{$prefix}.fields.*.id" => ['required', 'string', 'regex:'.self::FIELD_ID_PATTERN, 'distinct'],
            "{$prefix}.fields.*.kind" => ['sometimes', Rule::enum(ItemKind::class)],
            ...$this->themeRules("{$prefix}.theme"),
        ];

        foreach ($fields as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $kind = ItemKind::tryFrom((string) ($item['kind'] ?? ItemKind::Input->value));
            $path = "{$prefix}.fields.{$index}";

            $itemRules = match ($kind) {
                ItemKind::Block => $this->blockRules($path, $item, $fields, $index),
                ItemKind::Input => $this->inputRules($path, $item, $fields, $index),
                null => [],
            };

            $rules = [...$rules, ...$itemRules];
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
            "{$prefix}.fields.*.kind" => 'tipo de item',
            "{$prefix}.fields.*.type" => 'tipo do campo',
            "{$prefix}.fields.*.label" => 'rótulo do campo',
            "{$prefix}.fields.*.text" => 'texto',
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
            "{$prefix}.fields.*.rating" => 'configuração das estrelas',
            "{$prefix}.fields.*.rating.max" => 'quantidade de estrelas',
            "{$prefix}.fields.*.scale" => 'configuração da escala',
            "{$prefix}.fields.*.scale.min" => 'início da escala',
            "{$prefix}.fields.*.scale.max" => 'fim da escala',
            "{$prefix}.fields.*.scale.min_label" => 'rótulo do início',
            "{$prefix}.fields.*.scale.max_label" => 'rótulo do fim',
            "{$prefix}.fields.*.visibility" => 'condição',
            "{$prefix}.fields.*.rules" => 'regras',
            "{$prefix}.fields.*.rules.*.type" => 'tipo da regra',
            "{$prefix}.fields.*.rules.*.message" => 'mensagem da regra',
            "{$prefix}.theme" => 'tema',
            "{$prefix}.theme.colors.*" => 'cor',
            "{$prefix}.theme.cover.asset_id" => 'imagem de capa',
            "{$prefix}.theme.cover.overlay_opacity" => 'opacidade da camada sobre a capa',
            "{$prefix}.theme.logo.asset_id" => 'logo',
        ];
    }

    /**
     * @param  array<mixed>  $item
     * @param  list<mixed>  $fields
     * @return array<string, mixed>
     */
    private function blockRules(string $path, array $item, array $fields, int $index): array
    {
        $type = BlockType::tryFrom((string) ($item['type'] ?? ''));

        $rules = [
            "{$path}.type" => ['required', Rule::enum(BlockType::class), $this->pageBreakPlacement($fields, $index)],
            "{$path}.text" => $type?->hasText()
                ? ['required', 'string', 'max:'.$type->textMaxLength()]
                : ['prohibited'],
        ];

        foreach (self::INPUT_ONLY_KEYS as $key) {
            $rules["{$path}.{$key}"] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * @param  array<mixed>  $item
     * @param  list<mixed>  $fields
     * @return array<string, mixed>
     */
    private function inputRules(string $path, array $item, array $fields, int $index): array
    {
        $rules = [
            "{$path}.type" => ['required', Rule::enum(FieldType::class)],
            "{$path}.label" => ['required', 'string', 'max:200'],
            "{$path}.help_text" => ['nullable', 'string', 'max:500'],
            "{$path}.required" => ['sometimes', 'boolean'],
            "{$path}.text" => ['prohibited'],
            "{$path}.visibility" => ['nullable', $this->visibilityRule($fields, $index)],
            "{$path}.rules" => ['sometimes', 'array', 'max:'.self::MAX_RULES_PER_FIELD],
            "{$path}.rules.*" => ['array'],
            "{$path}.rules.*.type" => ['required', Rule::enum(RuleType::class)],
            "{$path}.rules.*.message" => ['nullable', 'string', 'max:200'],
        ];

        $type = FieldType::tryFrom((string) ($item['type'] ?? ''));

        if ($type === null) {
            return $rules;
        }

        $rules = [
            ...$rules,
            "{$path}.placeholder" => $type->acceptsPlaceholder() ? ['nullable', 'string', 'max:120'] : ['prohibited'],
            "{$path}.strictness" => $type->isText() ? ['nullable', Rule::enum(StrictnessPreset::class)] : ['prohibited'],
            // Por campo (e não com curinga): `distinct` com curinga compararia regras de campos diferentes.
            "{$path}.rules.*.type" => ['required', Rule::enum(RuleType::class), 'distinct', $this->ruleAppliesTo($type)],
            ...$this->optionRules($path, $type),
            ...$this->fileRules($path, $type),
            ...$this->rangeRules($path, $type, $item),
        ];

        $fieldRules = is_array($item['rules'] ?? null) ? $item['rules'] : [];

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
     * Estrelas (`rating`) e escala numérica (`scale`): resposta inteira num intervalo.
     *
     * @param  array<mixed>  $item
     * @return array<string, list<mixed>>
     */
    private function rangeRules(string $path, FieldType $type, array $item): array
    {
        $rules = [
            "{$path}.rating" => $type === FieldType::Rating ? ['sometimes', 'array'] : ['prohibited'],
            "{$path}.scale" => $type === FieldType::Scale ? ['required', 'array'] : ['prohibited'],
        ];

        if ($type === FieldType::Rating) {
            $rules["{$path}.rating.max"] = ['sometimes', 'integer', 'min:'.self::RATING_MIN_STARS, 'max:'.self::RATING_MAX_STARS];
        }

        if ($type === FieldType::Scale) {
            $min = $item['scale']['min'] ?? null;

            $rules["{$path}.scale.min"] = ['required', 'integer', 'min:'.self::SCALE_LOWEST, 'max:'.(self::SCALE_HIGHEST - 1)];
            $rules["{$path}.scale.max"] = ['required', 'integer', 'max:'.self::SCALE_HIGHEST, ...(is_int($min) ? ['gt:'."{$path}.scale.min"] : [])];
            $rules["{$path}.scale.min_label"] = ['nullable', 'string', 'max:'.self::SCALE_LABEL_MAX_LENGTH];
            $rules["{$path}.scale.max_label"] = ['nullable', 'string', 'max:'.self::SCALE_LABEL_MAX_LENGTH];
        }

        return $rules;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function themeRules(string $path): array
    {
        return [
            $path => ['sometimes', 'nullable', 'array'],
            "{$path}.preset" => ['sometimes', Rule::in(FormTheme::PRESETS)],
            "{$path}.colors" => ['sometimes', 'array'],
            ...array_combine(
                array_map(fn (string $key): string => "{$path}.colors.{$key}", FormTheme::COLOR_KEYS),
                array_fill(0, count(FormTheme::COLOR_KEYS), ['sometimes', 'string', 'regex:'.FormTheme::COLOR_PATTERN]),
            ),
            "{$path}.radius" => ['sometimes', Rule::in(FormTheme::RADII)],
            "{$path}.font" => ['sometimes', Rule::in(FormTheme::FONTS)],
            "{$path}.button_style" => ['sometimes', Rule::in(FormTheme::BUTTON_STYLES)],
            "{$path}.alignment" => ['sometimes', Rule::in(FormTheme::ALIGNMENTS)],
            "{$path}.cover" => ['sometimes', 'nullable', 'array'],
            "{$path}.cover.asset_id" => ["required_with:{$path}.cover", 'string', 'ulid'],
            "{$path}.cover.fit" => ['sometimes', Rule::in(FormTheme::COVER_FITS)],
            "{$path}.cover.overlay_opacity" => ['sometimes', 'integer', 'min:0', 'max:'.FormTheme::MAX_OVERLAY_OPACITY],
            "{$path}.logo" => ['sometimes', 'nullable', 'array'],
            "{$path}.logo.asset_id" => ["required_with:{$path}.logo", 'string', 'ulid'],
        ];
    }

    /**
     * Quebra de etapa não pode ser o primeiro nem o último item, nem vir logo
     * depois de outra: nenhuma etapa vazia.
     *
     * @param  list<mixed>  $fields
     * @return Closure(string, mixed, Closure): void
     */
    private function pageBreakPlacement(array $fields, int $index): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($fields, $index): void {
            if ($value !== BlockType::PageBreak->value) {
                return;
            }

            $previous = $fields[$index - 1] ?? null;
            $isPageBreak = fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === BlockType::PageBreak->value;

            if ($index === 0 || $index === count($fields) - 1 || $isPageBreak($previous)) {
                $fail('A quebra de etapa precisa ficar entre campos: não pode ser a primeira, a última nem vir logo após outra.');
            }
        };
    }

    /**
     * Condição de visibilidade. A origem precisa vir antes do campo: isso torna
     * ciclos impossíveis e garante que só se dependa de respostas já dadas (etapas).
     *
     * @param  list<mixed>  $fields
     * @return Closure(string, mixed, Closure): void
     */
    private function visibilityRule(array $fields, int $index): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($fields, $index): void {
            if ($value === null) {
                return;
            }

            $error = $this->visibilityError($value, $fields, $index);

            if ($error !== null) {
                $fail($error);
            }
        };
    }

    /**
     * @param  list<mixed>  $fields
     */
    private function visibilityError(mixed $visibility, array $fields, int $index): ?string
    {
        if (! is_array($visibility) || ! in_array($visibility['action'] ?? null, self::VISIBILITY_ACTIONS, strict: true)) {
            return 'A condição precisa dizer se o campo é mostrado ou ocultado.';
        }

        $when = $visibility['when'] ?? null;
        $sourceId = is_array($when) ? ($when['field'] ?? null) : null;

        if (! is_string($sourceId)) {
            return 'A condição precisa indicar o campo de origem.';
        }

        $sourceIndex = $this->indexOf($fields, $sourceId);

        if ($sourceIndex === null) {
            return 'A condição usa um campo que não existe.';
        }

        if ($sourceIndex >= $index) {
            return 'A condição precisa usar um campo que vem antes deste.';
        }

        /** @var array<string, mixed> $source */
        $source = $fields[$sourceIndex];
        $sourceType = SchemaItems::isInput($source) ? FieldType::tryFrom((string) ($source['type'] ?? '')) : null;
        $operators = $sourceType !== null ? VisibilityOperator::forSource($sourceType) : [];

        if ($operators === []) {
            return 'A condição não pode usar blocos nem campos de arquivo.';
        }

        $operator = VisibilityOperator::tryFrom((string) ($when['operator'] ?? ''));

        if ($operator === null || ! in_array($operator, $operators, strict: true)) {
            return 'Esse operador não vale para o campo escolhido na condição.';
        }

        /** @var FieldType $sourceType */
        return $this->conditionValueError($operator, $sourceType, $source, $when['value'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function conditionValueError(VisibilityOperator $operator, FieldType $sourceType, array $source, mixed $value): ?string
    {
        if (! $operator->needsValue()) {
            return $value === null ? null : 'Esse operador não usa valor.';
        }

        $valid = match (true) {
            $sourceType->isNumeric() => is_int($value) || is_float($value),
            $sourceType === FieldType::Date => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
            $sourceType->hasOptions() => is_string($value) && in_array($value, array_column((array) ($source['options'] ?? []), 'value'), strict: true),
            default => is_string($value) && trim($value) !== '' && mb_strlen($value) <= self::CONDITION_TEXT_MAX_LENGTH,
        };

        return $valid ? null : 'O valor da condição não combina com o campo escolhido.';
    }

    /**
     * @param  list<mixed>  $fields
     */
    private function indexOf(array $fields, string $id): ?int
    {
        foreach ($fields as $position => $item) {
            if (is_array($item) && ($item['id'] ?? null) === $id) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private function visibilityLimit(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $count = count(array_filter((array) $value, fn (mixed $item): bool => is_array($item) && is_array($item['visibility'] ?? null)));

            if ($count > self::MAX_VISIBILITY_RULES) {
                $fail('Use no máximo '.self::MAX_VISIBILITY_RULES.' condições por formulário.');
            }
        };
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
