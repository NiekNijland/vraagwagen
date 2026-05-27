<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class LedgerEntry
{
    public function __construct(
        public string $id,
        public Plan $plan,
        public RunnerResult $result,
    ) {
    }
}
