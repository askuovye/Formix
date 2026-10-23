<?php

declare(strict_types=1);

namespace App\Services\Health;

use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabaseHealthCheck implements HealthCheck
{
    public function name(): string
    {
        return 'database';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function passes(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
