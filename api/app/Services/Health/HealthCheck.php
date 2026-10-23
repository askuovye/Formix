<?php

declare(strict_types=1);

namespace App\Services\Health;

interface HealthCheck
{
    public function name(): string;

    /** Falha em um check crítico deixa a API indisponível (503); nos demais, apenas degradada. */
    public function isCritical(): bool;

    public function passes(): bool;
}
