<?php

declare(strict_types=1);

namespace App\Actions\Rdw;

use App\Ai\Agents\QueryProgramAgent;
use App\Enums\Locale;
use App\Services\QueryPlan\CostEstimator;
use App\Services\QueryPlan\CrossDatasetOverflowException;
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
use App\Services\QueryPlan\Refusal;
use App\Services\QueryPlan\RefusalReason;
use App\Services\QueryPlan\StepReference;
use App\Services\QueryPlan\StepReferenceException;
use App\Services\QueryPlan\StepReferenceResolver;
use App\Services\QueryPlan\TargetDataset;
use App\Services\QueryPlan\TokenUsage;
use App\Services\QueryPlan\WhereOp;

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
        $lookupIds = $this->lookupQueryIds($program);

        try {
            foreach ($program->queries as $query) {
                // A query whose plates feed a later `in` clause must fetch one row past the cap so an
                // over-cap brand is detectable (and refused) instead of silently truncating the join.
                // We always need the full plate set for the join, so the limit is forced even on the
                // off chance such a lookup is also the presented result.
                $plan = in_array($query->id, $lookupIds, true)
                    ? $this->withForcedLimit($query->plan, StepReferenceResolver::LIST_LIMIT + 1)
                    : $query->plan;

                $resolvedPlan = $this->referenceResolver->resolve($plan, $ledger);
                $ledger->record(new LedgerEntry($query->id, $resolvedPlan, $this->planRunner->run($resolvedPlan)));
            }

            return $this->present($program->presentation, $ledger, $model, $tokens, $estimatedCost);
        } catch (CrossDatasetOverflowException) {
            // __() can return array|string|null when the key resolves to a group; we only ever
            // store strings under this key, so narrow before passing the explanation through.
            $tooBroad = __('query.refusal.too_broad', [], $locale->value);

            return $this->unsupported(
                $ledger, $locale, $model, $tokens, $estimatedCost,
                is_string($tooBroad) ? $tooBroad : null,
                new Refusal(RefusalReason::TooBroad),
            );
        } catch (StepReferenceException|DerivationException) {
            return $this->unsupported($ledger, $locale, $model, $tokens, $estimatedCost);
        }
    }

    /**
     * Query ids whose result is consumed by a later `in` clause via a `{{qID.field}}` reference.
     *
     * @return list<string>
     */
    private function lookupQueryIds(QueryProgram $program): array
    {
        $ids = [];
        foreach ($program->queries as $query) {
            foreach ($query->plan->where as $clause) {
                if ($clause->op !== WhereOp::In) {
                    continue;
                }
                $reference = StepReference::tryParse($clause->value);
                if ($reference !== null && ! in_array($reference->queryId, $ids, true)) {
                    $ids[] = $reference->queryId;
                }
            }
        }

        return $ids;
    }

    private function withForcedLimit(Plan $plan, int $limit): Plan
    {
        return new Plan(
            dataset: $plan->dataset,
            where: $plan->where,
            select: $plan->select,
            groupBy: $plan->groupBy,
            aggregates: $plan->aggregates,
            orderBy: $plan->orderBy,
            limit: $limit,
            display: $plan->display,
            explanation: $plan->explanation,
        );
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

        // Re-stamp with the presented plan's display (authoritative: includes PlanFactory repairs).
        $presentation = new Presentation(
            resultRef: $presentation->resultRef,
            display: $source->plan->display,
            derive: $presentation->derive,
            explanation: $presentation->explanation,
            refusal: $presentation->refusal,
            followUps: $presentation->followUps,
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
        ?string $explanation = null,
        ?Refusal $refusal = null,
    ): QueryResult {
        if ($explanation === null) {
            $translation = __('query.unsupported', [], $locale->value);
            $explanation = is_string($translation) ? $translation : '';
        }

        // Dataset is a placeholder — `PlanRunner::run` short-circuits on Unsupported before touching it.
        $plan = new Plan(
            dataset: TargetDataset::RegisteredVehicles,
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
            presentation: new Presentation(
                resultRef: '',
                display: DisplayHint::Unsupported,
                derive: null,
                explanation: $explanation,
                refusal: $refusal ?? new Refusal(RefusalReason::OutOfScope),
            ),
            derived: null,
        );
    }
}
