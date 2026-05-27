<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class BucketExpression
{
    public function __construct(
        public string $alias,
        public string $expression,
    ) {
    }
}
