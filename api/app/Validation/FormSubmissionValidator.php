<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\FieldType;
use App\Enums\RuleType;
use Closure;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Valida uma resposta contra o schema de uma versão PUBLICADA (vindo do banco,
 * nunca do cliente) e devolve as respostas normalizadas, prontas para gravar.
 *
 * - Chaves que não são campos do schema são ignoradas.
 * - Erros vêm por id de campo (`errors[field_id]`), um por campo (`bail`).
 * - Campos de arquivo não passam por aqui: são tratados no fluxo de anexos.
 * - Custo limitado: texto com mais de MAX_TEXT_LENGTH caracteres é recusado
 *   antes das regras, e toda regra é linear no tamanho da entrada.
 */
final class FormSubmissionValidator
{
    public const MAX_EMAIL_LENGTH = 255;

    // Inteiro de até 15 dígitos com até 6 casas decimais (ponto ou vírgula na entrada).
    public const NUMBER_PATTERN = '/^-?\d{1,15}(\.\d{1,6})?$/';

    public function __construct(
        private readonly ValidationFactory $validationFactory,
        private readonly InputNormalizer $normalizer,
        private readonly RuleRegistry $registry,
    ) {}

    /**
     * @param  array{fields: list<array<string, mixed>>}  $schema
     * @param  array<string, mixed>  $input  respostas enviadas, por id de campo
     * @return array<string, mixed> respostas normalizadas por id (null = não respondido)
     *
     * @throws ValidationException
     */
    public function validate(array $schema, array $input): array
    {
        $data = [];
        $rules = [];
        $attributes = [];

        foreach ($schema['fields'] as $field) {
            $type = FieldType::from((string) $field['type']);

            if ($type === FieldType::File) {
                continue;
            }

            $id = (string) $field['id'];
            $data[$id] = $this->prepare($type, $input[$id] ?? null);
            $attributes[$id] = (string) $field['label'];
            $rules = [...$rules, ...$this->rulesFor($id, $type, $field)];
        }

        $this->validationFactory->make($data, $rules, [], $attributes)->validate();

        // Não usa validated(): ele reordena listas (D22) e a ordem das escolhas importa.
        return $data;
    }

    /**
     * Normaliza antes de validar; valores do tipo errado passam intactos para
     * falharem na regra de tipo.
     */
    private function prepare(FieldType $type, mixed $value): mixed
    {
        if (is_string($value) && strlen($value) > RuleType::MAX_TEXT_LENGTH * 4) {
            return $value;
        }

        $prepared = match ($type) {
            FieldType::ShortText, FieldType::Email => is_string($value) ? $this->normalizer->normalize($value) : $value,
            FieldType::LongText => is_string($value) ? $this->normalizer->normalize($value, multiline: true) : $value,
            FieldType::Number => $this->prepareNumber($value),
            FieldType::Date, FieldType::SingleChoice => is_string($value) ? trim($value) : $value,
            FieldType::MultipleChoice => $value === [] ? null : $value,
            FieldType::File => $value,
        };

        return $prepared === '' ? null : $prepared;
    }

    private function prepareNumber(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return is_float($value) && ! is_finite($value) ? 'invalido' : (string) $value;
        }

        return is_string($value) ? str_replace(',', '.', trim($value)) : $value;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, list<mixed>>
     */
    private function rulesFor(string $id, FieldType $type, array $field): array
    {
        $rules = [$id => ['bail', ($field['required'] ?? false) ? 'required' : 'nullable', ...$this->typeRules($type, $field)]];

        foreach ((array) ($field['rules'] ?? []) as $rule) {
            $rules[$id][] = $this->answerRule($rule);
        }

        if ($type === FieldType::MultipleChoice) {
            $rules["{$id}.*"] = ['bail', 'string', Rule::in($this->optionValues($field)), 'distinct'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<mixed>
     */
    private function typeRules(FieldType $type, array $field): array
    {
        return match ($type) {
            FieldType::ShortText, FieldType::LongText => ['string', 'max:'.RuleType::MAX_TEXT_LENGTH],
            FieldType::Email => ['string', 'max:'.self::MAX_EMAIL_LENGTH, 'email:rfc'],
            FieldType::Number => ['string', 'regex:'.self::NUMBER_PATTERN],
            FieldType::Date => ['string', 'date_format:Y-m-d'],
            FieldType::SingleChoice => ['string', Rule::in($this->optionValues($field))],
            FieldType::MultipleChoice => ['array', 'list', 'max:'.count($this->optionValues($field))],
            FieldType::File => [],
        };
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return Closure(string, mixed, Closure): void
     */
    private function answerRule(array $rule): Closure
    {
        $implementation = $this->registry->for(RuleType::from((string) $rule['type']));
        $customMessage = isset($rule['message']) ? (string) $rule['message'] : null;

        return function (string $attribute, mixed $value, Closure $fail) use ($implementation, $rule, $customMessage): void {
            if (! is_string($value) || $value === '' || $implementation->passes($value, $rule)) {
                return;
            }

            $fail($customMessage ?? $implementation->message($rule))->translate();
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private function optionValues(array $field): array
    {
        return array_values(array_map(
            fn (array $option): string => (string) $option['value'],
            (array) ($field['options'] ?? []),
        ));
    }
}
