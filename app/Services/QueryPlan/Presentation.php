<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class Presentation
{
    /** `resultRef` sentinel meaning "render the computed derive figure". */
    public const string DERIVED_REF = 'derived';

    /**
     * @param  list<string>  $followUps  Up to 3 complete next-step questions the user can click to ask next.
     */
    public function __construct(
        public string $resultRef,
        public DisplayHint $display,
        public ?Derive $derive,
        public string $explanation,
        public ?Refusal $refusal = null,
        public array $followUps = [],
    ) {}

    public function isDerived(): bool
    {
        return $this->derive !== null;
    }
}
