<?php

declare(strict_types=1);

namespace App\Http\Requests\Submissions;

use App\Enums\ExportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreExportRequest extends FormRequest
{
    use FiltersRespondent, ParsesPeriod;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->periodRules(),
            ...$this->respondentRules(),
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'format' => ['sometimes', Rule::enum(ExportFormat::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => 'data inicial', 'to' => 'data final', 'q' => 'busca', 'format' => 'formato', 'email' => 'email'];
    }

    public function exportFormat(): ExportFormat
    {
        return $this->enum('format', ExportFormat::class) ?? ExportFormat::Csv;
    }

    public function search(): ?string
    {
        return $this->filled('q') ? $this->string('q')->trim()->value() : null;
    }
}
