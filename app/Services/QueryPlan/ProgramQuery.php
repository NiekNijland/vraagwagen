<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class ProgramQuery
{
    public function __construct(
        public string $id,
        public Plan $plan,
    ) {
    }
}
