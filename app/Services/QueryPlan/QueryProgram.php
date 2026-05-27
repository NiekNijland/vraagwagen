<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class QueryProgram
{
    /**
     * @param list<ProgramQuery> $queries
     */
    public function __construct(
        public array $queries,
        public Presentation $presentation,
    ) {
    }
}
