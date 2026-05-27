<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * The declarative output of the model: an ordered list of sub-queries plus a
 * {@see Presentation}. PHP runs the queries in order (resolving dependent-step
 * references between them) and then renders the presentation. This replaces the
 * single {@see Plan} as the unit the agent produces.
 */
final readonly class QueryProgram
{
    /**
     * @param  list<ProgramQuery>  $queries  in execution order
     */
    public function __construct(
        public array $queries,
        public Presentation $presentation,
    ) {}
}
