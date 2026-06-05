<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class WhereClause
{
    /**
     * @param  list<string>  $values  populated only for WhereOp::In: a literal value list from the
     *                                model, or the expanded plate list after step-reference resolution
     */
    public function __construct(
        public string $field,
        public WhereOp $op,
        public string $value,
        public array $values = [],
    ) {}
}
