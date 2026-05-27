<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * The model's *request* for a deterministic combine (the computed result is a
 * {@see Derived}). Validated by {@see PresentationFactory} against the program's
 * query ids.
 *
 * A discriminated union expressed flatly, because the JSON-schema builder has no
 * per-property discriminator: a binary-scalar op ({@see DeriveOp::isBinaryScalar})
 * uses `numerator`/`denominator` (both query refs); {@see DeriveOp::GroupShare}
 * uses `source` + `selectorColumn`/`selectorValue`. The unused fields are null.
 */
final readonly class Derive
{
    public function __construct(
        public DeriveOp $op,
        public ?string $numerator = null,
        public ?string $denominator = null,
        public ?string $source = null,
        public ?string $selectorColumn = null,
        public ?string $selectorValue = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'op' => $this->op->value,
            'numerator' => $this->numerator,
            'denominator' => $this->denominator,
            'source' => $this->source,
            'selectorColumn' => $this->selectorColumn,
            'selectorValue' => $this->selectorValue,
        ];
    }
}
