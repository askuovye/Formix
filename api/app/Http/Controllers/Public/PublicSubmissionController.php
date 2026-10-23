<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Submissions\SubmitFormResponse;
use App\Actions\Submissions\UpdateSubmissionByRespondent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submissions\StoreSubmissionRequest;
use App\Http\Requests\Submissions\UpdateSubmissionRequest;
use App\Http\Resources\EditableSubmissionResource;
use App\Http\Resources\SubmissionReceiptResource;
use App\Models\Form;
use App\Models\Submission;
use App\Services\Submissions\PublishedFormVersions;
use App\Services\Submissions\RespondentEditGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PublicSubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request, Form $publicForm, SubmitFormResponse $submitFormResponse): JsonResponse
    {
        $receipt = $submitFormResponse($publicForm, $request->attempt());

        return (new SubmissionReceiptResource($receipt))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(
        Request $request,
        Form $publicForm,
        Submission $submission,
        RespondentEditGuard $guard,
        PublishedFormVersions $versions,
    ): EditableSubmissionResource {
        $guard->authorize($publicForm, $submission, $request->header(UpdateSubmissionRequest::EDIT_TOKEN_HEADER));

        return new EditableSubmissionResource([
            'submission' => $submission,
            'version' => $versions->get($publicForm, $submission->schema_version),
        ]);
    }

    public function update(
        UpdateSubmissionRequest $request,
        Form $publicForm,
        Submission $submission,
        UpdateSubmissionByRespondent $updateSubmission,
        PublishedFormVersions $versions,
    ): EditableSubmissionResource {
        $submission = $updateSubmission($publicForm, $submission, $request->editToken(), $request->answers(), $request->uploadedFiles());

        return new EditableSubmissionResource([
            'submission' => $submission,
            'version' => $versions->get($publicForm, $submission->schema_version),
        ]);
    }
}
