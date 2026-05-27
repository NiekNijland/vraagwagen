<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * The model's terminal answer: which ledgered query to display (or the sentinel
 * {@see self::DERIVED_REF} when a {@see Derive} figure is shown), how to render
 * it, and the one-sentence explanation (the only free text shown to the user, as
 * today). The displayed numbers come from the engine — never from the model.
 */
final readonly class Presentation
{
    /**
     * `resultRef` value meaning "render the computed derive figure" rather than
     * a ledgered query's rows.
     */
    public const string DERIVED_REF = 'derived';

    public function __construct(
        public string $resultRef,
        public DisplayHint $display,
        public ?Derive $derive,
        public string $explanation,
    ) {}

    public function isDerived(): bool
    {
        return $this->derive !== null;
    }
}
