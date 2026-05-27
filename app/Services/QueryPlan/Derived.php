<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * The deterministic figure computed by {@see Derivation} from one or two
 * ledgered query results. Carries both the headline `value` and the two
 * operands so the frontend can render context ("3.2% — 12,345 of 380,210")
 * without the model ever typing a number.
 *
 * `value` semantics by op: `percentage`/`ratio`/`groupShare` store the raw
 * quotient `numerator / denominator` (the view decides whether to ×100);
 * `difference` stores `numerator - denominator`; `sum` stores their sum. For
 * `groupShare`, `numerator` is the selected group's count and `denominator` the
 * column total.
 */
final readonly class Derived
{
    public function __construct(
        public DeriveOp $op,
        public float $value,
        public float $numerator,
        public float $denominator,
    ) {}

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
