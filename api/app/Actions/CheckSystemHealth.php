<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\HealthStatus;
use App\Services\Health\HealthCheck;
use App\Services\Health\HealthReport;

final class CheckSystemHealth
{
    /** @var list<HealthCheck> */
    private readonly array $checks;

    public function __construct(HealthCheck ...$checks)
    {
        $this->checks = array_values($checks);
    }

    public function __invoke(): HealthReport
    {
        $results = [];
        $status = HealthStatus::Ok;

        foreach ($this->checks as $check) {
            $passes = $check->passes();
            $results[$check->name()] = $passes;

            if ($passes || $status === HealthStatus::Down) {
                continue;
            }

            $status = $check->isCritical() ? HealthStatus::Down : HealthStatus::Degraded;
        }

        return new HealthReport($status, $results);
    }
}
