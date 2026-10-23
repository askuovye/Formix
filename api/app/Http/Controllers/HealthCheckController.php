<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CheckSystemHealth;
use App\Http\Resources\HealthReportResource;
use Illuminate\Http\JsonResponse;

final class HealthCheckController extends Controller
{
    public function __invoke(CheckSystemHealth $checkSystemHealth): JsonResponse
    {
        $report = $checkSystemHealth();

        return (new HealthReportResource($report))
            ->response()
            ->setStatusCode($report->status->httpStatus());
    }
}
