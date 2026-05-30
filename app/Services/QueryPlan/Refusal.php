<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * A structured "this cannot be answered" outcome: the machine-readable {@see RefusalReason} plus a
 * short list of concrete questions the registry *can* answer, which the UI renders as clickable
 * alternatives. The human-readable "why" lives in {@see Presentation::$explanation}.
 */
final readonly class Refusal
{
    /**
     * @param  list<string>  $suggestions  Answerable alternative questions, already in the user's locale.
     */
    public function __construct(
        public RefusalReason $reason,
        public array $suggestions = [],
    ) {}

    /**
     * @return array{reason: string, suggestions: list<string>}
     */
    public function toArray(): array
    {
        return [
            'reason' => $this->reason->value,
            'suggestions' => $this->suggestions,
        ];
    }
}
