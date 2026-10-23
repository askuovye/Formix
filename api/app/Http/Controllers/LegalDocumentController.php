<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LegalDocument;
use App\Http\Resources\LegalDocumentResource;

final class LegalDocumentController extends Controller
{
    public function __invoke(LegalDocument $document): LegalDocumentResource
    {
        return new LegalDocumentResource($document);
    }
}
