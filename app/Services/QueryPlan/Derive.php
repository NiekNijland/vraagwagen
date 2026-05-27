<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class Derive
{
    public function __construct(
        public DeriveOp $op,
        public ?string $numerator = null,
        public ?string $denominator = null,
        public ?string $source = null,
        public ?string $selectorColumn = null,
        public ?string $selectorValue = null,
    ) {
    }

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
