<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Operadores da lógica condicional (mostrar/ocultar campo). Quais valem para
 * cada tipo de campo de origem: `forSource()`.
 */
enum VisibilityOperator: string
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case LessThan = 'lt';
    case LessOrEqual = 'lte';
    case GreaterThan = 'gt';
    case GreaterOrEqual = 'gte';
    case Includes = 'includes';
    case Excludes = 'excludes';
    case Answered = 'answered';
    case NotAnswered = 'not_answered';

    public function needsValue(): bool
    {
        return $this !== self::Answered && $this !== self::NotAnswered;
    }

    /**
     * @return list<self>
     */
    public static function forSource(FieldType $source): array
    {
        $presence = [self::Answered, self::NotAnswered];
        $comparison = [self::Equals, self::NotEquals, self::LessThan, self::LessOrEqual, self::GreaterThan, self::GreaterOrEqual];

        return match (true) {
            $source->isNumeric(), $source === FieldType::Date => [...$comparison, ...$presence],
            $source === FieldType::MultipleChoice => [self::Includes, self::Excludes, ...$presence],
            $source === FieldType::File => [],
            default => [self::Equals, self::NotEquals, ...$presence],
        };
    }
}
