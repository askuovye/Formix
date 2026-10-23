<?php

declare(strict_types=1);

namespace App\Http\Requests\Submissions;

use Carbon\CarbonImmutable;

/**
 * Período `from`/`to` dos filtros. Data pura (AAAA-MM-DD) vale o dia inteiro no
 * horário de Brasília; data e hora (ISO 8601) é usada como veio.
 */
trait ParsesPeriod
{
    private static string $periodTimezone = 'America/Sao_Paulo';

    /**
     * @return array<string, list<string>>
     */
    protected function periodRules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function from(): ?CarbonImmutable
    {
        return $this->periodBoundary('from', endOfDay: false);
    }

    public function to(): ?CarbonImmutable
    {
        return $this->periodBoundary('to', endOfDay: true);
    }

    private function periodBoundary(string $key, bool $endOfDay): ?CarbonImmutable
    {
        $value = $this->input($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return CarbonImmutable::parse($value)->utc();
        }

        $day = CarbonImmutable::parse($value, self::$periodTimezone);

        return ($endOfDay ? $day->endOfDay() : $day->startOfDay())->utc();
    }
}
