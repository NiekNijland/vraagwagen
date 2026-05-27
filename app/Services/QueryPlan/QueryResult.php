<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * Everything a natural-language query produces. The `plan`/`rows`/`soql`/`url`
 * fields describe the *presented* result (the one query whose output is shown),
 * so the controller JSON and persistence stay backward-compatible with the
 * single-shot shape.
 *
 * Program mode additionally fills `steps` (every executed query, for the debug
 * pane + persistence), the resolved `presentation`, and — when the presentation
 * asked for a combine — the computed `derived` figure. In single mode those
 * stay empty/null and the response is byte-identical to before.
 */
final readonly class QueryResult
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $soql
     * @param  list<LedgerEntry>  $steps
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
    ) {}
}
