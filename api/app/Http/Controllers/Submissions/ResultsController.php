<?php

declare(strict_types=1);

namespace App\Http\Controllers\Submissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Submissions\ShowResultsRequest;
use App\Http\Resources\FormResultsResource;
use App\Models\Form;
use App\Models\User;
use App\Services\Submissions\FormResults;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * Resultados agregados de um formulário. Autorização na rota via `can:view` (FormPolicy).
 */
final class ResultsController extends Controller
{
    public function show(ShowResultsRequest $request, Form $form, #[CurrentUser] User $user, FormResults $results): FormResultsResource
    {
        return new FormResultsResource($results->build($user, $form, $request->filters($form)));
    }
}
