<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Resposta pertence a quem é dono do formulário. Outro usuário, ou formulário
 * excluído, recebe 404 (não confirma que o ID existe).
 */
final class SubmissionPolicy
{
    public function view(User $user, Submission $submission): Response
    {
        return $this->owner($user, $submission);
    }

    public function delete(User $user, Submission $submission): Response
    {
        return $this->owner($user, $submission);
    }

    private function owner(User $user, Submission $submission): Response
    {
        $form = $submission->form;

        return $form !== null && $form->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
