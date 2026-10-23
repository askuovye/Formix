<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class SubmissionFilters
{
    public function __construct(
        public ?string $formId = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public bool $oldestFirst = false,
        public int $perPage = 20,
    ) {}
}
