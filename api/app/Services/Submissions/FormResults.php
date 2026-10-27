<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use App\Actions\Submissions\ListOwnerSubmissions;
use App\Data\SubmissionFilters;
use App\Enums\DeviceType;
use App\Enums\FieldType;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Services\FormSchema\SchemaItems;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resultados agregados de um formulário (painel), com os mesmos filtros da lista.
 *
 * - Campos da versão publicada mais recente; o que só existiu em versões antigas fica de fora.
 * - Uma passada pelas respostas, em lotes (`lazyById`), lendo só as colunas
 *   necessárias; as contas são feitas em PHP para valerem igual no MariaDB e no SQLite.
 * - Anexos contados pela coleção do MediaLibrary (`field_{id}`), sem ler o JSON.
 * - Linha do tempo no fuso de Brasília: últimos 30 dias, ou o período filtrado
 *   (por semana acima de 90 dias).
 * - Tempo médio (medido no navegador, informativo) e aparelhos de quem respondeu.
 * - Com período filtrado, `previous` traz total e tempo médio do período anterior
 *   de mesmo tamanho (para a variação); sem período, não há com o que comparar.
 */
final class FormResults
{
    public const LATEST_TEXTS = 5;

    public const TEXT_PREVIEW_LENGTH = 200;

    public const DEFAULT_TIMELINE_DAYS = 30;

    public const WEEKLY_ABOVE_DAYS = 90;

    private const CHUNK_SIZE = 500;

    private const TIMEZONE = 'America/Sao_Paulo';

    private const TEXT_TYPES = [FieldType::ShortText, FieldType::LongText, FieldType::Email];

    public function __construct(
        private readonly ListOwnerSubmissions $listSubmissions,
        private readonly PublishedFormVersions $versions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $owner, Form $form, SubmissionFilters $filters): array
    {
        $fields = $form->schema_version > 0 ? SchemaItems::inputs($this->versions->get($form)['schema']) : [];
        $now = CarbonImmutable::now(self::TIMEZONE);
        [$timelineStart, $timelineEnd, $unit] = $this->timelineWindow($filters, $now);

        $stats = array_map(fn (array $field): array => $this->emptyStats($field), $fields);
        $buckets = $this->emptyBuckets($timelineStart, $timelineEnd, $unit);
        $total = 0;
        $edited = 0;
        $lastWeek = 0;
        $weekAgo = $now->subDays(7);
        $durationSum = 0;
        $durationCount = 0;
        $devices = array_fill_keys(array_map(fn (DeviceType $device): string => $device->value, DeviceType::cases()), 0);

        $query = $this->listSubmissions->query($owner, $filters)
            ->select(['id', 'form_id', 'data', 'duration_seconds', 'device', 'created_at', 'edited_at']);

        foreach ($query->lazyById(self::CHUNK_SIZE) as $submission) {
            $total++;
            $createdAt = CarbonImmutable::instance($submission->created_at ?? $now)->setTimezone(self::TIMEZONE);
            $edited += $submission->edited_at !== null ? 1 : 0;
            $lastWeek += $createdAt->greaterThanOrEqualTo($weekAgo) ? 1 : 0;

            if ($submission->duration_seconds !== null) {
                $durationSum += $submission->duration_seconds;
                $durationCount++;
            }

            if ($submission->device !== null) {
                $devices[$submission->device->value]++;
            }

            $bucket = $this->bucketKey($createdAt, $unit);
            if (array_key_exists($bucket, $buckets)) {
                $buckets[$bucket]++;
            }

            foreach ($fields as $index => $field) {
                $this->collect($stats[$index], $field, $submission->data[(string) $field['id']] ?? null, $submission);
            }
        }

        $filesAnswered = $this->filesAnswered($owner, $filters, $fields);

        return [
            'total' => $total,
            'last_7_days' => $lastWeek,
            'edited' => $edited,
            'average_duration_seconds' => $durationCount > 0 ? (int) round($durationSum / $durationCount) : null,
            'devices' => array_map(
                fn (string $device, int $count): array => ['device' => $device, 'count' => $count],
                array_keys($devices),
                array_values($devices),
            ),
            'previous' => $this->previousPeriod($owner, $filters, $now),
            'timeline' => [
                'unit' => $unit,
                'points' => array_map(
                    fn (string $date, int $count): array => ['date' => $date, 'count' => $count],
                    array_keys($buckets),
                    array_values($buckets),
                ),
            ],
            'fields' => array_map(
                fn (array $field, array $stat): array => $this->finish($field, $stat, $filesAnswered),
                $fields,
                $stats,
            ),
        ];
    }

    /**
     * Total e tempo médio do período anterior de mesmo tamanho (agregado no banco).
     *
     * @return array{total: int, average_duration_seconds: int|null}|null
     */
    private function previousPeriod(User $owner, SubmissionFilters $filters, CarbonImmutable $now): ?array
    {
        if ($filters->from === null) {
            return null;
        }

        $end = $filters->to ?? $now;
        $length = (int) $filters->from->diffInSeconds($end, absolute: true);
        $previous = new SubmissionFilters(
            formId: $filters->formId,
            from: $filters->from->subSeconds($length + 1),
            to: $filters->from->subSecond(),
            search: $filters->search,
            respondentEmail: $filters->respondentEmail,
        );

        $row = $this->listSubmissions->query($owner, $previous)->toBase()
            ->selectRaw('count(*) as total, avg(duration_seconds) as average')
            ->first();

        $average = $row->average ?? null;

        return [
            'total' => (int) ($row->total ?? 0),
            'average_duration_seconds' => is_numeric($average) ? (int) round((float) $average) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function emptyStats(array $field): array
    {
        $stat = ['answered' => 0];

        return match (FieldType::tryFrom((string) $field['type'])) {
            FieldType::SingleChoice, FieldType::MultipleChoice => [...$stat, 'counts' => []],
            FieldType::Rating, FieldType::Scale => [...$stat, 'counts' => [], 'sum' => 0],
            FieldType::Number => [...$stat, 'min' => null, 'max' => null, 'sum' => 0.0],
            FieldType::Date => [...$stat, 'earliest' => null, 'latest' => null],
            default => [...$stat, 'latest' => []],
        };
    }

    /**
     * @param  array<string, mixed>  $stat
     * @param  array<string, mixed>  $field
     */
    private function collect(array &$stat, array $field, mixed $value, Submission $submission): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        $type = FieldType::tryFrom((string) $field['type']);

        switch ($type) {
            case FieldType::SingleChoice:
            case FieldType::MultipleChoice:
                foreach (is_array($value) ? $value : [$value] as $option) {
                    $key = (string) (is_scalar($option) ? $option : '');
                    $stat['counts'][$key] = ($stat['counts'][$key] ?? 0) + 1;
                }
                break;

            case FieldType::Rating:
            case FieldType::Scale:
                if (! is_numeric($value)) {
                    return;
                }
                $key = (int) $value;
                $stat['counts'][$key] = ($stat['counts'][$key] ?? 0) + 1;
                $stat['sum'] += $key;
                break;

            case FieldType::Number:
                if (! is_numeric($value)) {
                    return;
                }
                $number = (float) $value;
                $stat['min'] = $stat['min'] === null ? $number : min($stat['min'], $number);
                $stat['max'] = $stat['max'] === null ? $number : max($stat['max'], $number);
                $stat['sum'] += $number;
                break;

            case FieldType::Date:
                if (! is_string($value)) {
                    return;
                }
                // AAAA-MM-DD: a ordem do texto é a ordem das datas.
                $stat['earliest'] = $stat['earliest'] === null || $value < $stat['earliest'] ? $value : $stat['earliest'];
                $stat['latest'] = $stat['latest'] === null || $value > $stat['latest'] ? $value : $stat['latest'];
                break;

            default:
                if (! in_array($type, self::TEXT_TYPES, strict: true) || ! is_scalar($value)) {
                    return;
                }
                // lazyById vem em ordem de envio: as últimas da lista são as mais novas.
                $stat['latest'][] = [
                    'submission_id' => $submission->id,
                    'value' => Str::limit((string) $value, self::TEXT_PREVIEW_LENGTH),
                    'created_at' => $submission->created_at?->toIso8601String(),
                ];
                if (count($stat['latest']) > self::LATEST_TEXTS) {
                    array_shift($stat['latest']);
                }
        }

        $stat['answered']++;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $stat
     * @param  array<string, int>  $filesAnswered
     * @return array<string, mixed>
     */
    private function finish(array $field, array $stat, array $filesAnswered): array
    {
        $id = (string) $field['id'];
        $type = FieldType::tryFrom((string) $field['type']);
        $base = ['id' => $id, 'label' => (string) $field['label'], 'type' => (string) $field['type'], 'answered' => $stat['answered']];

        return match ($type) {
            FieldType::SingleChoice, FieldType::MultipleChoice => [...$base, 'options' => $this->options($field, $stat['counts'])],
            FieldType::Rating, FieldType::Scale => [
                ...$base,
                'average' => $stat['answered'] > 0 ? round($stat['sum'] / $stat['answered'], 2) : null,
                'distribution' => $this->distribution($field, $type, $stat['counts']),
            ],
            FieldType::Number => [
                ...$base,
                'min' => $stat['min'],
                'max' => $stat['max'],
                'average' => $stat['answered'] > 0 ? round($stat['sum'] / $stat['answered'], 2) : null,
            ],
            FieldType::Date => [...$base, 'earliest' => $stat['earliest'], 'latest' => $stat['latest']],
            FieldType::File => [...$base, 'answered' => $filesAnswered[Submission::mediaCollectionFor($id)] ?? 0],
            default => [...$base, 'latest' => array_reverse($stat['latest'])],
        };
    }

    /**
     * Opções na ordem do formulário; valores que não existem mais (versões antigas) no fim.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, int>  $counts
     * @return list<array{value: string, label: string, count: int}>
     */
    private function options(array $field, array $counts): array
    {
        $options = [];

        foreach (is_array($field['options'] ?? null) ? $field['options'] : [] as $option) {
            $value = (string) ($option['value'] ?? '');
            $options[] = ['value' => $value, 'label' => (string) ($option['label'] ?? $value), 'count' => $counts[$value] ?? 0];
            unset($counts[$value]);
        }

        foreach ($counts as $value => $count) {
            $options[] = ['value' => (string) $value, 'label' => (string) $value, 'count' => $count];
        }

        return $options;
    }

    /**
     * Todos os valores possíveis, inclusive os que ninguém escolheu.
     *
     * @param  array<string, mixed>  $field
     * @param  array<int, int>  $counts
     * @return list<array{value: int, count: int}>
     */
    private function distribution(array $field, FieldType $type, array $counts): array
    {
        if ($type === FieldType::Rating) {
            [$min, $max] = [1, (int) ($field['rating']['max'] ?? 5)];
        } else {
            [$min, $max] = [(int) ($field['scale']['min'] ?? 0), (int) ($field['scale']['max'] ?? 10)];
        }

        $distribution = [];

        foreach (range($min, max($min, $max)) as $value) {
            $distribution[] = ['value' => $value, 'count' => $counts[$value] ?? 0];
        }

        return $distribution;
    }

    /**
     * Respostas com pelo menos um arquivo, por coleção (campo).
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, int>
     */
    private function filesAnswered(User $owner, SubmissionFilters $filters, array $fields): array
    {
        $collections = [];

        foreach ($fields as $field) {
            if ($field['type'] === FieldType::File->value) {
                $collections[] = Submission::mediaCollectionFor((string) $field['id']);
            }
        }

        if ($collections === []) {
            return [];
        }

        /** @var array<string, int> */
        return Media::query()
            ->where('model_type', (new Submission)->getMorphClass())
            ->whereIn('model_id', $this->listSubmissions->query($owner, $filters)->select('id'))
            ->whereIn('collection_name', $collections)
            ->groupBy('collection_name')
            ->selectRaw('collection_name, count(distinct model_id) as answered')
            ->pluck('answered', 'collection_name')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: 'day'|'week'}
     */
    private function timelineWindow(SubmissionFilters $filters, CarbonImmutable $now): array
    {
        $end = ($filters->to !== null ? CarbonImmutable::instance($filters->to)->setTimezone(self::TIMEZONE) : $now)->startOfDay();
        $start = $filters->from !== null
            ? CarbonImmutable::instance($filters->from)->setTimezone(self::TIMEZONE)->startOfDay()
            : $end->subDays(self::DEFAULT_TIMELINE_DAYS - 1);

        $unit = $start->diffInDays($end) > self::WEEKLY_ABOVE_DAYS ? 'week' : 'day';

        return [$start, $end, $unit];
    }

    /**
     * @param  'day'|'week'  $unit
     * @return array<string, int>
     */
    private function emptyBuckets(CarbonImmutable $start, CarbonImmutable $end, string $unit): array
    {
        $buckets = [];
        $cursor = $unit === 'week' ? $start->startOfWeek(CarbonInterface::MONDAY) : $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $buckets[$cursor->toDateString()] = 0;
            $cursor = $unit === 'week' ? $cursor->addWeek() : $cursor->addDay();
        }

        return $buckets;
    }

    /**
     * @param  'day'|'week'  $unit
     */
    private function bucketKey(CarbonImmutable $date, string $unit): string
    {
        return ($unit === 'week' ? $date->startOfWeek(CarbonInterface::MONDAY) : $date)->toDateString();
    }
}
