<?php

declare(strict_types=1);

namespace App\Http\Controllers\Forms;

use App\Actions\Forms\CloseForm;
use App\Actions\Forms\PublishForm;
use App\Http\Controllers\Controller;
use App\Http\Resources\FormResource;
use App\Models\Form;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

final class FormLifecycleController extends Controller
{
    public function publish(Form $form, #[CurrentUser] User $user, PublishForm $publishForm): FormResource
    {
        return new FormResource($publishForm($form, $user));
    }

    public function close(Form $form, CloseForm $closeForm): FormResource
    {
        return new FormResource($closeForm($form));
    }
}
