<?php

declare(strict_types=1);

namespace App\Http\Controllers\Submissions;

use App\Actions\Submissions\DeleteSubmission;
use App\Actions\Submissions\ListOwnerSubmissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submissions\ListSubmissionsRequest;
use App\Http\Resources\SubmissionResource;
use App\Http\Resources\SubmissionSummaryResource;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Services\Submissions\SubmissionsPdfRenderer;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Painel de respostas do dono. Autorização nas rotas via `can:` (SubmissionPolicy).
 */
final class SubmissionController extends Controller
{
    public function index(ListSubmissionsRequest $request, #[CurrentUser] User $user, ListOwnerSubmissions $listSubmissions): AnonymousResourceCollection
    {
        return SubmissionSummaryResource::collection($listSubmissions($user, $request->filters()));
    }

    public function show(Submission $submission): SubmissionResource
    {
        return new SubmissionResource($submission->load(['form', 'media', 'revisions']));
    }

    /**
     * PDF de uma resposta, gerado na hora (uma página ou poucas: cabe na requisição).
     */
    public function pdf(Submission $submission, SubmissionsPdfRenderer $renderer): Response
    {
        $submission->load(['form', 'media']);
        /** @var Form $form */
        $form = $submission->form;
        $filename = Str::slug($form->title) ?: 'resposta';

        return response($renderer->render($form, [$submission]), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}-{$submission->id}.pdf\"",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function destroy(Submission $submission, DeleteSubmission $deleteSubmission): Response
    {
        $deleteSubmission($submission);

        return response()->noContent();
    }
}
