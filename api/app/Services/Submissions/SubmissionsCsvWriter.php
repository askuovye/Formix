<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Submission;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Escreve as respostas de um formulário em CSV.
 *
 * - Colunas: campos de **todas** as versões publicadas (os da versão mais nova
 *   primeiro, depois os que só existiram em versões antigas), para nenhuma
 *   resposta perder dados quando o formulário mudou.
 * - Formato do Excel em português: separador `;` e BOM UTF-8 (acentos corretos).
 * - Injeção de fórmula (CSV injection): valor começando com = + - @ tab ou CR
 *   recebe um apóstrofo na frente, para o Excel tratá-lo como texto. Isso
 *   inclui números negativos ("'-10"): é o custo de nunca executar uma fórmula
 *   digitada por um respondente.
 */
final class SubmissionsCsvWriter
{
    public const DELIMITER = ';';

    private const UTF8_BOM = "\xEF\xBB\xBF";

    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    private const FILE_NAMES_SEPARATOR = ' | ';

    private const DATE_FORMAT = 'd/m/Y H:i';

    private const DISPLAY_TIMEZONE = 'America/Sao_Paulo';

    public function __construct(private readonly SubmissionPresenter $presenter) {}

    /**
     * @param  resource  $handle
     * @param  iterable<Submission>  $submissions  com `media` carregado
     * @return int linhas de resposta escritas
     */
    public function write($handle, Form $form, iterable $submissions): int
    {
        $versions = FormVersion::query()->where('form_id', $form->id)->orderByDesc('version')->get();
        $columns = $this->columns(array_values($versions->map(fn (FormVersion $version): array => $version->schema)->all()));
        $schemas = $versions->mapWithKeys(fn (FormVersion $version): array => [$version->version => $version->schema]);

        fwrite($handle, self::UTF8_BOM);
        $this->putRow($handle, ['ID', 'Enviada em', 'Editada em', 'Versão', ...array_values($columns)]);

        $count = 0;

        foreach ($submissions as $submission) {
            /** @var array{fields: list<array<string, mixed>>} $schema */
            $schema = $schemas[$submission->schema_version] ?? ['fields' => []];
            $values = $this->values($schema, $submission);

            $this->putRow($handle, [
                $submission->id,
                $this->date($submission->created_at),
                $this->date($submission->edited_at),
                (string) $submission->schema_version,
                ...array_map(fn (string $fieldId): string => $values[$fieldId] ?? '', array_keys($columns)),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * @param  list<array{fields: list<array<string, mixed>>}>  $schemasNewestFirst
     * @return array<string, string> id do campo => rótulo (da versão mais nova em que aparece)
     */
    private function columns(array $schemasNewestFirst): array
    {
        $columns = [];

        foreach ($schemasNewestFirst as $schema) {
            foreach ($schema['fields'] as $field) {
                $columns[(string) $field['id']] ??= (string) $field['label'];
            }
        }

        return $columns;
    }

    /**
     * @param  array{fields: list<array<string, mixed>>}  $schema
     * @return array<string, string> id do campo => valor legível ('' quando não respondido)
     */
    private function values(array $schema, Submission $submission): array
    {
        $values = [];

        foreach ($this->presenter->present($schema, $submission->data) as $row) {
            $values[$row['field_id']] = $row['answered'] ? $row['value'] : '';
        }

        foreach ($this->presenter->presentFiles($schema, $submission->media) as $group) {
            $values[$group['field_id']] = implode(self::FILE_NAMES_SEPARATOR, array_column($group['files'], 'name'));
        }

        return $values;
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $row
     */
    private function putRow($handle, array $row): void
    {
        fputcsv($handle, array_map($this->neutralizeFormula(...), $row), self::DELIMITER, '"', '');
    }

    private function neutralizeFormula(string $value): string
    {
        foreach (self::FORMULA_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return "'".$value;
            }
        }

        return $value;
    }

    private function date(?DateTimeInterface $date): string
    {
        return $date === null ? '' : CarbonImmutable::instance($date)->setTimezone(self::DISPLAY_TIMEZONE)->format(self::DATE_FORMAT);
    }
}
