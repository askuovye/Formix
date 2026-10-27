<?php

declare(strict_types=1);

namespace App\Http\Requests\Submissions;

use App\Data\SubmissionFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListSubmissionsRequest extends FormRequest
{
    use ParsesPeriod;

    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 50;

    public const SORTS = ['newest', 'oldest'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Só formulários do próprio usuário (e não excluídos) são filtros válidos.
            'form' => ['sometimes', 'string', Rule::exists('forms', 'id')
                ->where('user_id', $this->user()?->getAuthIdentifier())
                ->whereNull('deleted_at')],
            ...$this->periodRules(),
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(self::SORTS)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'cursor' => ['sometimes', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['form' => 'formulário', 'from' => 'data inicial', 'to' => 'data final', 'sort' => 'ordenação', 'q' => 'busca'];
    }

    public function filters(): SubmissionFilters
    {
        return new SubmissionFilters(
            formId: $this->filled('form') ? $this->string('form')->value() : null,
            from: $this->from(),
            to: $this->to(),
            oldestFirst: $this->input('sort') === 'oldest',
            perPage: $this->integer('per_page', self::DEFAULT_PER_PAGE),
            search: $this->filled('q') ? $this->string('q')->trim()->value() : null,
        );
    }
}
