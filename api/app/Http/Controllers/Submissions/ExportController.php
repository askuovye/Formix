<?php

declare(strict_types=1);

namespace App\Http\Controllers\Submissions;

use App\Actions\Submissions\RequestSubmissionsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submissions\StoreExportRequest;
use App\Http\Resources\ExportResource;
use App\Models\Export;
use App\Models\Form;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

final class ExportController extends Controller
{
    public function store(StoreExportRequest $request, Form $form, #[CurrentUser] User $user, RequestSubmissionsExport $requestExport): JsonResponse
    {
        $export = $requestExport($user, $form, $request->from(), $request->to());

        return (new ExportResource($export))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(Export $export): ExportResource
    {
        return new ExportResource($export);
    }

    /**
     * Rota com link assinado (gerado em ExportResource para o dono logado).
     */
    public function download(Export $export): StreamedResponse
    {
        if (! $export->isDownloadable()) {
            throw new GoneHttpException;
        }

        $filename = 'respostas-'.$export->form_id.'-'.$export->created_at?->format('Y-m-d').'.csv';

        return Storage::disk((string) $export->disk)->download((string) $export->path, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
