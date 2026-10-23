<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Export;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class ExportPolicy
{
    public function view(User $user, Export $export): Response
    {
        return $export->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
