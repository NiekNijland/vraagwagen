<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

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
     * @return list<LedgerEntry>
     */
    public function all(): array
    {
        return array_values($this->entries);
    }
}
