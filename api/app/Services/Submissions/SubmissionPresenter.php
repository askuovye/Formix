<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use App\Enums\FieldType;
use Closure;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Transforma as respostas gravadas em pares rótulo/valor legíveis, na ordem do
 * schema da versão com que foram enviadas. Valores de escolha viram os rótulos
 * das opções. Não escapa nada: quem exibe (email, CSV) escapa no seu formato.
 */
final class SubmissionPresenter
{
    public const NOT_ANSWERED = '(não respondido)';

    /**
     * @param  array{fields: list<array<string, mixed>>}  $schema
     * @param  array<string, mixed>  $data
     * @return list<array{field_id: string, label: string, value: string, multiline: bool, answered: bool}>
     */
    public function present(array $schema, array $data): array
    {
        $rows = [];

        foreach ($schema['fields'] as $field) {
            $type = FieldType::from((string) $field['type']);

            // Anexos são apresentados à parte (presentFiles).
            if ($type === FieldType::File) {
                continue;
            }

            $value = $this->format($type, $field, $data[(string) $field['id']] ?? null);

            $rows[] = [
                'field_id' => (string) $field['id'],
                'label' => (string) $field['label'],
                'value' => $value ?? self::NOT_ANSWERED,
                'multiline' => $type === FieldType::LongText,
                'answered' => $value !== null,
            ];
        }

        return $rows;
    }

    /**
     * Anexos agrupados por campo de arquivo, na ordem do schema.
     *
     * @param  array{fields: list<array<string, mixed>>}  $schema
     * @param  iterable<Media>  $media
     * @param  (Closure(Media): string)|null  $urlFor  sem gerador, os arquivos saem sem link
     * @return list<array{field_id: string, label: string, files: list<array{name: string, size: string, url: string|null}>}>
     */
    public function presentFiles(array $schema, iterable $media, ?Closure $urlFor = null): array
    {
        $byField = [];

        foreach ($media as $item) {
            $byField[(string) $item->getCustomProperty('field_id')][] = [
                'name' => $item->name,
                'size' => $this->humanSize((int) $item->size),
                'url' => $urlFor !== null ? $urlFor($item) : null,
            ];
        }

        $groups = [];

        foreach ($schema['fields'] as $field) {
            $files = $byField[(string) $field['id']] ?? [];

            if ($field['type'] === FieldType::File->value && $files !== []) {
                $groups[] = ['field_id' => (string) $field['id'], 'label' => (string) $field['label'], 'files' => $files];
            }
        }

        return $groups;
    }

    private function humanSize(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? number_format($bytes / (1024 * 1024), 1, ',', '.').' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function format(FieldType $type, array $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($type) {
            FieldType::SingleChoice => $this->optionLabel($field, (string) $value),
            FieldType::MultipleChoice => implode(', ', array_map(
                fn (mixed $item): string => $this->optionLabel($field, (string) $item),
                (array) $value,
            )),
            FieldType::Date => $this->brazilianDate((string) $value),
            default => (string) $value,
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function optionLabel(array $field, string $value): string
    {
        foreach ((array) ($field['options'] ?? []) as $option) {
            if ($option['value'] === $value) {
                return (string) $option['label'];
            }
        }

        return $value;
    }

    private function brazilianDate(string $isoDate): string
    {
        $parts = explode('-', $isoDate);

        return count($parts) === 3 ? "{$parts[2]}/{$parts[1]}/{$parts[0]}" : $isoDate;
    }
}
