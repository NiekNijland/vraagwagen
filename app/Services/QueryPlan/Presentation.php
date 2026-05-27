<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class Presentation
{
    /** `resultRef` sentinel meaning "render the computed derive figure". */
    public const string DERIVED_REF = 'derived';

    public function __construct(
        public string $resultRef,
        public DisplayHint $display,
        public ?Derive $derive,
        public string $explanation,
    ) {
    }

    public function isDerived(): bool
    {
        return $this->derive !== null;
    }
}
