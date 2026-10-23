<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Http\Response;

enum HealthStatus: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Down = 'down';

    public function httpStatus(): int
    {
        return $this === self::Down ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_OK;
    }
}
