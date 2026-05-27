<?php

declare(strict_types=1);

namespace App\Actions\Rdw;

use App\Ai\Agents\QueryProgramAgent;
use App\Enums\Locale;
use App\Services\QueryPlan\CostEstimator;
use App\Services\QueryPlan\Derivation;
use App\Services\QueryPlan\DerivationException;
use App\Services\QueryPlan\Derive;
use App\Services\QueryPlan\Derived;
use App\Services\QueryPlan\DeriveOp;
use App\Services\QueryPlan\DisplayHint;
use App\Services\QueryPlan\LedgerEntry;
use App\Services\QueryPlan\Plan;
use App\Services\QueryPlan\PlanRunner;
use App\Services\QueryPlan\Presentation;
use App\Services\QueryPlan\QueryLedger;
use App\Services\QueryPlan\QueryProgram;
use App\Services\QueryPlan\QueryProgramFactory;
use App\Services\QueryPlan\QueryResult;
use App\Services\QueryPlan\StepReferenceException;
use App\Services\QueryPlan\StepReferenceResolver;
use App\Services\QueryPlan\TokenUsage;

/**
 * Turns a natural-language question into an executed {@see QueryResult}.
 *
 * Every question is a {@see QueryProgram}: a simple
 * "how many Toyotas" is a one-query program; a ratio is two queries plus a
 * derive; "how many of my car's model" is a dependent step. The queries run in
 * order, dependent-step references are resolved between them, then the
 * presentation is resolved (optionally computing a deterministic figure). A
 * reference or derivation that fails on real data degrades to a graceful
 * `unsupported` answer rather than a 500.
 */
class RunNaturalLanguageQuery
{
    public function __construct(
        private readonly PlanRunner $planRunner,
        private readonly CostEstimator $costEstimator,
        private readonly QueryProgramFactory $programFactory,
        private readonly StepReferenceResolver $referenceResolver,
        private readonly Derivation $derivation,
    ) {}

    public function execute(string $userPrompt, Locale $locale): QueryResult
    {
        $response = QueryProgramAgent::make(locale: $locale)->ask($userPrompt);

        /** @var array<string, mixed> $raw */
        $raw = $response->structured;
        $program = $this->programFactory->fromArray($raw);

        $model = $response->meta->model ?? '';
        $tokens = TokenUsage::fromUsage($response->usage);
        $estimatedCost = $this->costEstimator->estimate($model, $response->usage);

        $ledger = new QueryLedger;

        try {
            foreach ($program->queries as $query) {
                $resolvedPlan = $this->referenceResolver->resolve($query->plan, $ledger);
                $ledger->record(new LedgerEntry($query->id, $resolvedPlan, $this->planRunner->run($resolvedPlan)));
            }

            return $this->present($program->presentation, $ledger, $model, $tokens, $estimatedCost);
        } catch (StepReferenceException|DerivationException) {
            return $this->unsupported($ledger, $locale, $model, $tokens, $estimatedCost);
        }
    }

    private function present(
        Presentation $presentation,
        QueryLedger $ledger,
        string $model,
        TokenUsage $tokens,
        ?float $estimatedCost,
    ): QueryResult {
        $derived = $presentation->derive !== null
            ? $this->computeDerived($presentation->derive, $ledger)
            : null;

        $source = $this->presentedEntry($presentation, $ledger);

        // The presented query's plan display is authoritative: it reflects the
        // repairs PlanFactory applies (e.g. a bogus count downgraded to
        // unsupported) that the model's raw presentation.display does not.
        // Re-stamp the presentation with it so the rendered displayHint and the
        // persisted presentation.display can never diverge.
        $presentation = new Presentation(
            resultRef: $presentation->resultRef,
            display: $source->plan->display,
            derive: $presentation->derive,
            explanation: $presentation->explanation,
        );

        return new QueryResult(
            plan: $source->plan,
            rows: $source->result->rows,
            soql: $source->result->soql,
            url: $source->result->url,
            model: $model,
            tokens: $tokens,
            estimatedCost: $estimatedCost,
            steps: $ledger->all(),
            presentation: $presentation,
            derived: $derived,
        );
    }

    /**
     * The ledger entry whose plan/rows back the presented view. For a plain
     * passthrough that is `resultRef`; for a derive it is the primary operand
     * (the grouped source, or the numerator) so the debug pane and persisted
     * plan still reflect a real query.
     */
    private function presentedEntry(Presentation $presentation, QueryLedger $ledger): LedgerEntry
    {
        $derive = $presentation->derive;
        $id = match (true) {
            $derive === null => $presentation->resultRef,
            $derive->op === DeriveOp::GroupShare => (string) $derive->source,
            default => (string) $derive->numerator,
        };

        $entry = $ledger->get($id);
        if ($entry === null) {
            throw new DerivationException(sprintf('Presented query "%s" was not executed.', $id));
        }

        return $entry;
    }

    private function computeDerived(Derive $derive, QueryLedger $ledger): Derived
    {
        if ($derive->op === DeriveOp::GroupShare) {
            $source = $this->requireEntry($ledger, (string) $derive->source);

            return $this->derivation->groupShare(
                $source->result->rows,
                (string) $derive->selectorColumn,
                (string) $derive->selectorValue,
                $this->countColumn($source),
            );
        }

        $numerator = $this->scalarValue($this->requireEntry($ledger, (string) $derive->numerator));
        $denominator = $this->scalarValue($this->requireEntry($ledger, (string) $derive->denominator));

        return match ($derive->op) {
            DeriveOp::Percentage => $this->derivation->percentage($numerator, $denominator),
            DeriveOp::Ratio => $this->derivation->ratio($numerator, $denominator),
            DeriveOp::Difference => $this->derivation->difference($numerator, $denominator),
            default => $this->derivation->sum($numerator, $denominator),
        };
    }

    private function requireEntry(QueryLedger $ledger, string $id): LedgerEntry
    {
        $entry = $ledger->get($id);
        if ($entry === null) {
            throw new DerivationException(sprintf('Derive references query "%s", which was not executed.', $id));
        }

        return $entry;
    }

    private function scalarValue(LedgerEntry $entry): float
    {
        $rows = $entry->result->rows;
        if (count($rows) !== 1) {
            throw new DerivationException(sprintf(
                'Query "%s" must return exactly one row to derive a scalar, got %d.',
                $entry->id,
                count($rows),
            ));
        }

        $row = $rows[0];
        $alias = $entry->plan->aggregates[0]->alias ?? null;
        $value = $alias !== null && array_key_exists($alias, $row) ? $row[$alias] : reset($row);

        return (float) $value;
    }

    private function countColumn(LedgerEntry $entry): string
    {
        return $entry->plan->aggregates[0]->alias ?? 'n';
    }

    private function unsupported(
        QueryLedger $ledger,
        Locale $locale,
        string $model,
        TokenUsage $tokens,
        ?float $estimatedCost,
    ): QueryResult {
        $translation = __('query.unsupported', [], $locale->value);
        $explanation = is_string($translation) ? $translation : '';

        $plan = new Plan(
            where: [], select: [], groupBy: [], aggregates: [], orderBy: [],
            limit: 1, display: DisplayHint::Unsupported, explanation: $explanation,
        );

        return new QueryResult(
            plan: $plan,
            rows: [],
            soql: [],
            url: '',
            model: $model,
            tokens: $tokens,
            estimatedCost: $estimatedCost,
            steps: $ledger->all(),
            presentation: null,
            derived: null,
        );
    }
}
