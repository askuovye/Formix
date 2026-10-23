<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de campo do schema. Adicionar um tipo = caso aqui + regras aceitas em
 * RuleType::appliesTo() + validação da resposta (motor) + componente no renderer.
 */
enum FieldType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Email = 'email';
    case Number = 'number';
    case Date = 'date';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case File = 'file';
    case Rating = 'rating';
    case Scale = 'scale';

    public function isText(): bool
    {
        return $this === self::ShortText || $this === self::LongText;
    }

    public function hasOptions(): bool
    {
        return $this === self::SingleChoice || $this === self::MultipleChoice;
    }

    /**
     * Resposta numérica comparável (lógica condicional com < <= > >=).
     */
    public function isNumeric(): bool
    {
        return in_array($this, [self::Number, self::Rating, self::Scale], strict: true);
    }

    /**
     * Resposta inteira dentro de um intervalo configurado no campo.
     */
    public function isRange(): bool
    {
        return $this === self::Rating || $this === self::Scale;
    }

    public function acceptsPlaceholder(): bool
    {
        return in_array($this, [self::ShortText, self::LongText, self::Email, self::Number], strict: true);
    }
}
