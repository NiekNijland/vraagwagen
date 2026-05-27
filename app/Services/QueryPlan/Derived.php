<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class Derived
{
    public function __construct(
        public DeriveOp $op,
        public float $value,
        public float $numerator,
        public float $denominator,
    ) {
    }

    /**
     * @return array{op: string, value: float, numerator: float, denominator: float}
     */
    public function toArray(): array
    {
        return [
            'op' => $this->op->value,
            'value' => $this->value,
            'numerator' => $this->numerator,
            'denominator' => $this->denominator,
        ];
    }
}
