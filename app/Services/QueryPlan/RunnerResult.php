<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class RunnerResult
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $soql
     */
    public function __construct(
        public array $rows,
        public array $soql,
        public string $url,
    ) {
    }
}
