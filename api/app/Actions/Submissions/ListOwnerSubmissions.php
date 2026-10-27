<?php

declare(strict_types=1);

namespace App\Actions\Submissions;

use App\Data\SubmissionFilters;
use App\Models\Submission;
use App\Models\User;
use App\Support\SearchText;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final class ListOwnerSubmissions
{
    /**
     * Respostas dos formulários do dono (excluídos ficam de fora). ULID é ordenável
     * pelo tempo de criação, então ordenar por `id` equivale a ordenar pela data
     * de envio e usa o índice da FK (form_id, id).
     *
     * @return CursorPaginator<int, Submission>
     */
    public function __invoke(User $owner, SubmissionFilters $filters): CursorPaginator
    {
        return $this->query($owner, $filters)
            ->with('form')
            ->withCount('media')
            ->orderBy('id', $filters->oldestFirst ? 'asc' : 'desc')
            ->cursorPaginate($filters->perPage);
    }

    /**
     * Também usada pela exportação, para aplicar os mesmos filtros.
     *
     * @return Builder<Submission>
     */
    public function query(User $owner, SubmissionFilters $filters): Builder
    {
        return Submission::query()
            // Subconsulta: a resposta só aparece se o formulário for deste dono e não estiver excluído.
            ->whereIn('form_id', $owner->forms()->select('id'))
            ->when($filters->formId, fn (Builder $query, string $formId) => $query->where('form_id', $formId))
            ->when($filters->from, fn (Builder $query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters->to, fn (Builder $query, $to) => $query->where('created_at', '<=', $to))
            ->when(
                $filters->search,
                fn (Builder $query, string $search) => SearchText::whereContains($query, 'search_text', SearchText::normalize($search)),
            );
    }
}
