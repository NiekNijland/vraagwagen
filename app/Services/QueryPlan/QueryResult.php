<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class QueryResult
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $soql
     * @param list<LedgerEntry> $steps
     */
    public function __construct(
        public Plan $plan,
        public array $rows,
        public array $soql,
        public string $url,
        public string $model,
        public TokenUsage $tokens,
        public ?float $estimatedCost,
        public array $steps = [],
        public ?Presentation $presentation = null,
        public ?Derived $derived = null,
    ) {
    }
}
