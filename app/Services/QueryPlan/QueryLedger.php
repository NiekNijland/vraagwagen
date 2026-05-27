<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * Request-scoped record of every executed query in a run, keyed by id
 * (`q1`, `q2`, …). Full rows live here — out of the model's context — so the
 * UI and persistence can read them while the model only ever sees bounded
 * summaries (loop mode) or nothing at all (declarative mode).
 *
 * Resolves {@see StepReference}s and {@see Presentation} `resultRef`s, and
 * provides the ordered transcript for {@see QueryResult}.
 */
final class QueryLedger
{
    /** @var array<string, LedgerEntry> */
    private array $entries = [];

    public function record(LedgerEntry $entry): void
    {
        $this->entries[$entry->id] = $entry;
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }

    public function get(string $id): ?LedgerEntry
    {
        return $this->entries[$id] ?? null;
    }

    /**
     * The entries in insertion (execution) order.
     *
     * @return list<LedgerEntry>
     */
    public function all(): array
    {
        return array_values($this->entries);
    }
}
