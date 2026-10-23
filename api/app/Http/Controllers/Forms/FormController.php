<?php

declare(strict_types=1);

namespace App\Http\Controllers\Forms;

use App\Actions\Forms\CreateForm;
use App\Actions\Forms\DeleteForm;
use App\Actions\Forms\ListUserForms;
use App\Actions\Forms\UpdateForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\ListFormsRequest;
use App\Http\Requests\Forms\StoreFormRequest;
use App\Http\Requests\Forms\UpdateFormRequest;
use App\Http\Resources\FormResource;
use App\Http\Resources\FormSummaryResource;
use App\Models\Form;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Autorização (dono do formulário) aplicada nas rotas via `can:` (FormPolicy).
 */
final class FormController extends Controller
{
    public function index(ListFormsRequest $request, #[CurrentUser] User $user, ListUserForms $listUserForms): AnonymousResourceCollection
    {
        return FormSummaryResource::collection($listUserForms($user, $request->status(), $request->perPage()));
    }

    public function store(StoreFormRequest $request, #[CurrentUser] User $user, CreateForm $createForm): JsonResponse
    {
        $form = $createForm($user, $request->string('title')->value(), $request->input('description'));

        return (new FormResource($form))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Form $form): FormResource
    {
        return new FormResource($form);
    }

    public function update(UpdateFormRequest $request, Form $form, UpdateForm $updateForm): FormResource
    {
        return new FormResource($updateForm($form, $request->formData()));
    }

    public function destroy(Form $form, DeleteForm $deleteForm): Response
    {
        $deleteForm($form);

        return response()->noContent();
    }
}
