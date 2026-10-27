<?php

declare(strict_types=1);

namespace App\Http\Requests\Submissions;

use App\Data\SubmissionFilters;
use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;

final class ShowResultsRequest extends FormRequest
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => 'data inicial', 'to' => 'data final', 'q' => 'busca', 'email' => 'email'];
    }

    public function filters(Form $form): SubmissionFilters
    {
        return new SubmissionFilters(
            formId: $form->id,
            from: $this->from(),
            to: $this->to(),
            search: $this->filled('q') ? $this->string('q')->trim()->value() : null,
            respondentEmail: $this->respondentEmail(),
        );
    }
}
