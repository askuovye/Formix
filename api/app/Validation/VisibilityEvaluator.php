<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\FieldType;
use App\Enums\VisibilityOperator;
use App\Services\FormSchema\SchemaItems;

/**
 * Decide quais campos estão ocultos pela lógica condicional (D32).
 *
 * Mesmo contrato do avaliador TypeScript (casos em fixtures/validation-cases.json
 * → "visibility"):
 *  - percorre os campos na ordem do schema; a origem de uma condição sempre vem
 *    antes do campo (garantido ao salvar), então uma passada resolve cadeias;
 *  - origem oculta conta como não respondida;
 *  - "não respondido" = null, texto só com espaços ou lista vazia;
 *  - comparações (eq, lt, includes…) com origem não respondida são falsas;
 *  - texto compara sem diferenciar maiúsculas e acentos; número aceita vírgula;
 *    data compara `AAAA-MM-DD`; valor que não é do tipo esperado torna a condição falsa.
 */
final class VisibilityEvaluator
{
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    public function __construct(private readonly InputNormalizer $normalizer) {}

    /**
     * @param  array{fields: list<array<string, mixed>>}  $schema
     * @param  array<string, mixed>  $answers  respostas por id de campo
     * @return list<string> ids dos campos ocultos
     */
    public function hiddenFieldIds(array $schema, array $answers): array
    {
        $types = [];
        $hidden = [];

        foreach (SchemaItems::inputs($schema) as $field) {
            $id = (string) $field['id'];
            $types[$id] = FieldType::from((string) $field['type']);
            $visibility = $field['visibility'] ?? null;

            if (! is_array($visibility)) {
                continue;
            }

            $source = (string) $visibility['when']['field'];
            $sourceType = $types[$source] ?? null;
            $sourceValue = in_array($source, $hidden, strict: true) ? null : ($answers[$source] ?? null);

            $met = $sourceType !== null && $this->matches(
                $sourceType,
                VisibilityOperator::from((string) $visibility['when']['operator']),
                $sourceValue,
                $visibility['when']['value'] ?? null,
            );

            $visible = $visibility['action'] === 'show' ? $met : ! $met;

            if (! $visible) {
                $hidden[] = $id;
            }
        }

        return $hidden;
    }

    private function matches(FieldType $type, VisibilityOperator $operator, mixed $value, mixed $expected): bool
    {
        $answered = $this->isAnswered($value);

        if ($operator === VisibilityOperator::Answered || $operator === VisibilityOperator::NotAnswered) {
            return $answered === ($operator === VisibilityOperator::Answered);
        }

        if (! $answered) {
            return false;
        }

        return match (true) {
            $type->isNumeric() => $this->compare($operator, $this->number($value), $this->number($expected)),
            $type === FieldType::Date => $this->compare($operator, $this->date($value), $this->date($expected)),
            $type === FieldType::MultipleChoice => $this->includes($operator, $value, $expected),
            default => $this->equalsText($operator, $value, $expected),
        };
    }

    private function compare(VisibilityOperator $operator, float|string|null $left, float|string|null $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        $order = $left <=> $right;

        return match ($operator) {
            VisibilityOperator::Equals => $order === 0,
            VisibilityOperator::NotEquals => $order !== 0,
            VisibilityOperator::LessThan => $order < 0,
            VisibilityOperator::LessOrEqual => $order <= 0,
            VisibilityOperator::GreaterThan => $order > 0,
            VisibilityOperator::GreaterOrEqual => $order >= 0,
            default => false,
        };
    }

    private function includes(VisibilityOperator $operator, mixed $value, mixed $expected): bool
    {
        if (! is_array($value) || ! is_scalar($expected)) {
            return false;
        }

        $contains = in_array((string) $expected, array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $value), strict: true);

        return match ($operator) {
            VisibilityOperator::Includes => $contains,
            VisibilityOperator::Excludes => ! $contains,
            default => false,
        };
    }

    private function equalsText(VisibilityOperator $operator, mixed $value, mixed $expected): bool
    {
        if (! is_scalar($value) || ! is_scalar($expected)) {
            return false;
        }

        $same = $this->fold((string) $value) === $this->fold((string) $expected);

        return match ($operator) {
            VisibilityOperator::Equals => $same,
            VisibilityOperator::NotEquals => ! $same,
            default => false,
        };
    }

    private function isAnswered(mixed $value): bool
    {
        return match (true) {
            $value === null => false,
            is_string($value) => trim($value) !== '',
            is_array($value) => $value !== [],
            default => true,
        };
    }

    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = is_string($value) ? str_replace(',', '.', trim($value)) : null;

        return $normalized !== null && is_numeric($normalized) ? (float) $normalized : null;
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match(self::DATE_PATTERN, trim($value)) === 1 ? trim($value) : null;
    }

    private function fold(string $value): string
    {
        return $this->normalizer->fold($this->normalizer->normalize($value));
    }
}
