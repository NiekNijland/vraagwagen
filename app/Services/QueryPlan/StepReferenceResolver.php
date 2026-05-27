<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * Substitutes dependent-step references ({@see StepReference}, e.g.
 * `{{q1.Brand}}`) in a {@see Plan}'s `where` values with concrete values from
 * the {@see QueryLedger}, just before the plan runs.
 *
 * This is what keeps value-chaining (look up a plate, then count that model)
 * deterministic and out of the model: the model emits the token, PHP resolves
 * it. A referenced query must be a single-row lookup; anything else is a
 * {@see StepReferenceException}.
 */
final class StepReferenceResolver
{
    public function resolve(Plan $plan, QueryLedger $ledger): Plan
    {
        $where = array_map(
            fn (WhereClause $clause): WhereClause => $this->resolveClause($clause, $ledger),
            $plan->where,
        );

        return new Plan(
            where: $where,
            select: $plan->select,
            groupBy: $plan->groupBy,
            aggregates: $plan->aggregates,
            orderBy: $plan->orderBy,
            limit: $plan->limit,
            display: $plan->display,
            explanation: $plan->explanation,
        );
    }

    private function resolveClause(WhereClause $clause, QueryLedger $ledger): WhereClause
    {
        $reference = StepReference::tryParse($clause->value);
        if ($reference === null) {
            return $clause;
        }

        return new WhereClause(
            field: $clause->field,
            op: $clause->op,
            value: $this->resolveReference($reference, $ledger),
        );
    }

    private function resolveReference(StepReference $reference, QueryLedger $ledger): string
    {
        $entry = $ledger->get($reference->queryId);
        if ($entry === null) {
            throw new StepReferenceException(sprintf('Reference to unknown query "%s".', $reference->queryId));
        }

        $rows = $entry->result->rows;
        if (count($rows) !== 1) {
            throw new StepReferenceException(sprintf(
                'Reference "%s" expects query "%s" to return exactly one row, got %d.',
                $reference->token(),
                $reference->queryId,
                count($rows),
            ));
        }

        $row = $rows[0];
        if (! array_key_exists($reference->field, $row)) {
            throw new StepReferenceException(sprintf(
                'Query "%s" did not return column "%s".',
                $reference->queryId,
                $reference->field,
            ));
        }

        $value = $row[$reference->field];
        if ($value === null) {
            throw new StepReferenceException(sprintf(
                'Column "%s" of query "%s" is null.',
                $reference->field,
                $reference->queryId,
            ));
        }

        return (string) $value;
    }
}
