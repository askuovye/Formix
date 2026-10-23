<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\FormAvailability;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicFormResource;
use App\Models\Form;
use App\Services\Submissions\PublishedFormVersions;

final class PublicFormController extends Controller
{
    public function __invoke(Form $publicForm, PublishedFormVersions $versions): PublicFormResource
    {
        return new PublicFormResource([
            'form' => $publicForm,
            'version' => $versions->get($publicForm),
            'availability' => FormAvailability::of($publicForm),
        ]);
    }
}
