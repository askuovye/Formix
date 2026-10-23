<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Documentos aceitos no cadastro. Título, versão e arquivo em config/legal.php.
 */
enum LegalDocument: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';

    // Visível no texto para que o operador perceba a falta de configuração.
    public const NOT_CONFIGURED = '[não informado pelo operador desta instância]';

    public function title(): string
    {
        return (string) config("legal.documents.{$this->value}.title");
    }

    public function version(): string
    {
        return (string) config("legal.documents.{$this->value}.version");
    }

    public function updatedAt(): string
    {
        return (string) config("legal.documents.{$this->value}.updated_at");
    }

    /**
     * Texto com os marcadores {{chave}} trocados pelos dados do operador da instância.
     */
    public function content(): string
    {
        $template = (string) file_get_contents((string) config("legal.documents.{$this->value}.path"));

        return strtr($template, self::operatorPlaceholders());
    }

    /**
     * @return array<string, string>
     */
    private static function operatorPlaceholders(): array
    {
        /** @var array<string, string|null> $operator */
        $operator = config('legal.operator');

        $placeholders = [];

        foreach ($operator as $key => $value) {
            $placeholders['{{'.$key.'}}'] = filled($value) ? (string) $value : self::NOT_CONFIGURED;
        }

        return $placeholders;
    }
}
