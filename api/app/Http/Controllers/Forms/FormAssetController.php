<?php

declare(strict_types=1);

namespace App\Http\Controllers\Forms;

use App\Enums\FormAssetKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StoreFormAssetRequest;
use App\Http\Resources\FormAssetResource;
use App\Models\Form;
use App\Models\FormAsset;
use App\Services\FormAssets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FormAssetController extends Controller
{
    public function store(StoreFormAssetRequest $request, Form $form, FormAssets $assets): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $asset = $assets->store($form, $file, FormAssetKind::from($request->string('kind')->value()));

        return (new FormAssetResource($asset))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Form $form, FormAsset $asset, FormAssets $assets): Response
    {
        $assets->delete($asset);

        return response()->noContent();
    }

    /**
     * Pública: a imagem aparece na página do formulário. Formulário excluído → 404.
     */
    public function show(FormAsset $asset, FormAssets $assets): StreamedResponse
    {
        if ($asset->form === null) {
            throw new NotFoundHttpException;
        }

        return $assets->response($asset);
    }
}
