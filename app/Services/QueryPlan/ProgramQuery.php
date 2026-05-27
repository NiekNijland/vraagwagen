<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * One identified sub-query in a {@see QueryProgram}: a stable id (`q1`, `q2`, …)
 * the {@see Presentation} and dependent-step {@see StepReference}s point at, and
 * the {@see Plan} to run for it.
 */
final readonly class ProgramQuery
{
    public function __construct(
        public string $id,
        public Plan $plan,
    ) {}
}
