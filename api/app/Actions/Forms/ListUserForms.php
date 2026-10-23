<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListUserForms
{
    /**
     * Mais recentes primeiro. ULID é ordenável pelo tempo de criação, então a
     * ordenação por `id` é estável e usa o índice da FK (user_id, id).
     *
     * @return CursorPaginator<int, Form>
     */
    public function __invoke(User $owner, ?FormStatus $status, int $perPage): CursorPaginator
    {
        return $owner->forms()
            ->when($status, fn ($query, FormStatus $status) => $query->where('status', $status))
            // Última resposta numa subconsulta só (sem N+1); o total já é a coluna submissions_count.
            ->withMax('submissions', 'created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }
}
