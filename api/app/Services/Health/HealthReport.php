<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Enums\HealthStatus;

final readonly class HealthReport
{
    /**
     * @param  array<string, bool>  $checks  nome do check => passou
     */
    public function __construct(
        public HealthStatus $status,
        public array $checks,
    ) {}
}
