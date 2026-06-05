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
use App\Services\QueryPlan\EmptyStepReferenceException;
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
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RunNaturalLanguageQuery
{
    private const int PROGRAM_CACHE_TTL_SECONDS = 86_400;

    public function __construct(
        private readonly PlanRunner $planRunner,
        private readonly CostEstimator $costEstimator,
        private readonly QueryProgramFactory $programFactory,
        private readonly StepReferenceResolver $referenceResolver,
        private readonly Derivation $derivation,
        private readonly Repository $cache,
    ) {
    }

    public function execute(string $userPrompt, Locale $locale): QueryResult
    {
        [$program, $model, $tokens, $estimatedCost] = $this->resolveProgram($userPrompt, $locale);

        $ledger = new QueryLedger();

        // A refusal must short-circuit before anything runs: the model sometimes attaches real
        // queries to an unsupported presentation, and presenting their rows next to a refusal
        // explanation shows the user a confident answer to a question it just declined.
        if ($program->presentation->display === DisplayHint::Unsupported) {
            return $this->unsupported(
                $ledger, $locale, $model, $tokens, $estimatedCost,
                $program->presentation->explanation !== '' ? $program->presentation->explanation : null,
                $program->presentation->refusal,
            );
        }

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
        } catch (EmptyStepReferenceException) {
            // A lookup matched nothing (unknown plate, brand spelled differently, unregistered
            // field) — the question was fine, the registry just holds no data for it.
            $noMatches = __('query.refusal.no_matches', [], $locale->value);

            return $this->unsupported(
                $ledger, $locale, $model, $tokens, $estimatedCost,
                is_string($noMatches) ? $noMatches : null,
                new Refusal(RefusalReason::NoSuchData),
            );
        } catch (StepReferenceException|DerivationException $e) {
            // A malformed multi-query program: a derive operand that wasn't a scalar, a reference
            // that resolved to the wrong shape, etc. The question itself is usually answerable
            // ("what % of Teslas are white?") — the model just botched the program — so surface the
            // controller's "try rephrasing" path rather than a confident, misleading refusal.
            throw new InvalidArgumentException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @return array{QueryProgram, string, TokenUsage, ?float}
     */
    private function resolveProgram(string $userPrompt, Locale $locale): array
    {
        $cacheKey = $this->programCacheKey($userPrompt, $locale);
        $cached = $this->cache->get($cacheKey);

        if (
            is_array($cached)
            && isset($cached['program'])
            && is_array($cached['program'])
        ) {
            return [
                $this->programFactory->fromArray($cached['program']),
                is_string($cached['model'] ?? null) ? $cached['model'] : '',
                new TokenUsage(prompt: 0, completion: 0, cacheRead: 0, thought: 0),
                null,
            ];
        }

        $response = QueryProgramAgent::make(locale: $locale)->ask($userPrompt);

        /** @var array<string, mixed> $raw */
        $raw = $response->structured;
        $program = $this->programFactory->fromArray($raw);
        $model = $response->meta->model ?? '';

        $this->cache->put(
            $cacheKey,
            [
                'program' => $raw,
                'model' => $model,
            ],
            self::PROGRAM_CACHE_TTL_SECONDS,
        );

        return [
            $program,
            $model,
            TokenUsage::fromUsage($response->usage),
            $this->costEstimator->estimate($model, $response->usage),
        ];
    }

    private function programCacheKey(string $userPrompt, Locale $locale): string
    {
        return sprintf(
            'rdw:query-program:%s:%s',
            $locale->value,
            sha1(mb_strtolower(Str::squish($userPrompt))),
        );
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
            followUps: $this->resolveFollowUps($presentation->followUps, $ledger),
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
     * Substitute `{{qID.Field}}` tokens the model embeds in follow-up questions with the resolved
     * value from the executed step ("{{q1.Brand}}" → "KAWASAKI"). A follow-up whose token cannot
     * be resolved is dropped — leaking a raw template token to the user is worse than one fewer chip.
     *
     * @param list<string> $followUps
     * @return list<string>
     */
    private function resolveFollowUps(array $followUps, QueryLedger $ledger): array
    {
        $resolved = [];
        foreach ($followUps as $followUp) {
            $failed = false;
            $substituted = (string) preg_replace_callback(
                '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\.\s*([A-Za-z][A-Za-z0-9_]*)\s*\}\}/',
                function (array $matches) use ($ledger, &$failed): string {
                    $value = $ledger->get($matches[1])?->result->rows[0][$matches[2]] ?? null;
                    if ($value === null || is_array($value)) {
                        $failed = true;

                        return '';
                    }

                    return (string) $value;
                },
                $followUp,
            );

            if (! $failed) {
                $resolved[] = $substituted;
            }
        }

        return $resolved;
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
