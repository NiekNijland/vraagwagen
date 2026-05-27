<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * One executed step in a {@see QueryProgram}: its id, the *resolved* {@see Plan}
 * that ran (references already substituted), and the {@see RunnerResult}. Held
 * by the {@see QueryLedger} for reference resolution, presentation, the debug
 * pane, and persistence.
 */
final readonly class LedgerEntry
{
    public function __construct(
        public string $id,
        public Plan $plan,
        public RunnerResult $result,
    ) {}
}
