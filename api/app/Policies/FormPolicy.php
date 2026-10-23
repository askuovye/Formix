<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Form;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Formulário de outra pessoa responde 404, não 403: não revela que o ID existe (IDOR).
 */
final class FormPolicy
{
    public function view(User $user, Form $form): Response
    {
        return $this->owner($user, $form);
    }

    public function update(User $user, Form $form): Response
    {
        return $this->owner($user, $form);
    }

    public function delete(User $user, Form $form): Response
    {
        return $this->owner($user, $form);
    }

    public function publish(User $user, Form $form): Response
    {
        return $this->owner($user, $form);
    }

    public function close(User $user, Form $form): Response
    {
        return $this->owner($user, $form);
    }

    private function owner(User $user, Form $form): Response
    {
        return $form->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
