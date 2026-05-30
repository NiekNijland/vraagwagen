<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use BackedEnum;
use InvalidArgumentException;
use NiekNijland\RDW\Query\QueryBuilder;
use NiekNijland\RDW\Query\SortDirection;
use NiekNijland\RDW\Rdw;
use NiekNijland\RDW\Records\RegisteredVehicle;
use NiekNijland\RDW\Records\RegisteredVehicleFuel;

/**
 * Translates a {@see Plan} into the RDW client's typed {@see QueryBuilder}. Owns the SoQL details:
 * field resolution, value casting via {@see FieldCaster}, the `::number` cast for text-stored
 * numeric columns (driven by {@see SocrataStorageTypes}), the case- and separator-insensitive
 * `contains` predicate, and the strict numeric literal guard that keeps `whereRaw` interpolation
 * safe.
 */
final readonly class QueryAssembler
{
    /** Strict decimal literal: optional sign, digits, optional fractional part. No `1e5`, no whitespace, no hex. */
    private const string NUMERIC_LITERAL_PATTERN = '/^-?\d+(\.\d+)?$/';

    public function __construct(
        private Rdw $rdw,
        private SocrataStorageTypes $storageTypes,
        private FieldCaster $caster,
    ) {}

    /**
     * @param  array<string, BucketExpression>  $buckets
     * @return QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>
     */
    public function assemble(Plan $plan, array $buckets): QueryBuilder
    {
        $builder = $this->builderFor($plan->dataset);
        $builder = $this->applyWhere($builder, $plan->where, $plan->dataset);
        $builder = $this->applySelectAndGroupBy($builder, $plan, $buckets);
        $builder = $this->applyAggregates($builder, $plan->aggregates, $plan->dataset);
        $builder = $this->applyOrderBy($builder, $plan->orderBy, $plan->aggregates, $buckets, $plan->dataset);

        if ($plan->limit !== null) {
            $builder = $builder->limit($plan->limit);
        }

        return $builder;
    }

    /**
     * @param  list<GroupKey>  $groupBy
     * @return array<string, BucketExpression>
     */
    public function buildBucketsByField(array $groupBy, TargetDataset $dataset): array
    {
        $schema = $this->rdw->schemas()->get($dataset->datasetId());
        $out = [];

        foreach ($groupBy as $key) {
            if ($key->bucket === Bucket::None) {
                continue;
            }

            $descriptor = $schema->byEnumCase[$key->field] ?? null;
            if ($descriptor === null) {
                throw new InvalidArgumentException(sprintf('Unknown field "%s" for dataset %s.', $key->field, $dataset->value));
            }

            $fn = match ($key->bucket) {
                Bucket::Year => 'date_trunc_y',
                Bucket::Month => 'date_trunc_ym',
                Bucket::Day => 'date_trunc_ymd',
            };

            $out[$key->field] = new BucketExpression(
                alias: $key->field,
                expression: sprintf('%s(%s)', $fn, $descriptor->rdwKey),
            );
        }

        return $out;
    }

    /**
     * @return QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>
     */
    private function builderFor(TargetDataset $dataset): QueryBuilder
    {
        return match ($dataset) {
            TargetDataset::RegisteredVehicles => $this->rdw->registeredVehicles(),
            TargetDataset::RegisteredVehicleFuels => $this->rdw->registeredVehicleFuels(),
        };
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>  $builder
     * @param  list<WhereClause>  $clauses
     * @return QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>
     */
    private function applyWhere(QueryBuilder $builder, array $clauses, TargetDataset $dataset): QueryBuilder
    {
        foreach ($clauses as $clause) {
            $field = $this->resolveField($clause->field, $dataset);

            $builder = match ($clause->op) {
                WhereOp::Equals => $this->applyComparison($builder, $field, $clause->value, '=', $dataset),
                WhereOp::NotEquals => $this->applyComparison($builder, $field, $clause->value, '!=', $dataset),
                WhereOp::GreaterThan => $this->applyComparison($builder, $field, $clause->value, '>', $dataset),
                WhereOp::GreaterThanOrEqual => $this->applyComparison($builder, $field, $clause->value, '>=', $dataset),
                WhereOp::LessThan => $this->applyComparison($builder, $field, $clause->value, '<', $dataset),
                WhereOp::LessThanOrEqual => $this->applyComparison($builder, $field, $clause->value, '<=', $dataset),
                WhereOp::Contains => $builder->whereRaw($this->normalisedContainsExpression($field, $clause->value)),
                WhereOp::StartsWith => $builder->whereStartsWith($field, $clause->value),
                WhereOp::In => $this->applyIn($builder, $field, $clause->values, $dataset),
            };
        }

        return $builder;
    }

    /**
     * Emits `col::number <op> n` for text-stored numerics, otherwise the typed `where()` path.
     * NB: `!=` on a text-stored numeric drops NULL/empty cells (SoQL evaluates `''::number != n`
     * to NULL); acceptable since empty cells aren't meaningfully numeric.
     *
     * @param  QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>  $builder
     * @return QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>
     */
    private function applyComparison(QueryBuilder $builder, BackedEnum $field, string $rawValue, string $operator, TargetDataset $dataset): QueryBuilder
    {
        if ($this->needsNumericCast($field, $dataset)) {
            return $builder->whereRaw(sprintf(
                '%s::number %s %s',
                $field->value,
                $operator,
                $this->assertNumericLiteral($field, $rawValue),
            ));
        }

        return $builder->where($field, $this->caster->cast($field, $rawValue, $dataset), $operator);
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>  $builder
     * @param  list<string>  $values
     * @return QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>
     */
    private function applyIn(QueryBuilder $builder, BackedEnum $field, array $values, TargetDataset $dataset): QueryBuilder
    {
        if ($values === []) {
            throw new InvalidArgumentException(sprintf(
                'WhereOp::In on field "%s" requires a non-empty values list.',
                $field->name,
            ));
        }

        if ($this->needsNumericCast($field, $dataset)) {
            $literals = array_map(
                fn (string $v): string => $this->assertNumericLiteral($field, $v),
                $values,
            );

            return $builder->whereRaw(sprintf(
                '%s::number IN (%s)',
                $field->value,
                implode(', ', $literals),
            ));
        }

        return $builder->whereIn($field, $this->caster->castMany($field, $values, $dataset));
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>  $builder
     * @param  array<string, BucketExpression>  $buckets
     * @return QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>
     */
    private function applySelectAndGroupBy(QueryBuilder $builder, Plan $plan, array $buckets): QueryBuilder
    {
        foreach ($plan->select as $name) {
            $builder = $builder->select($this->resolveField($name, $plan->dataset));
        }

        foreach ($plan->groupBy as $key) {
            $bucket = $buckets[$key->field] ?? null;
            if ($bucket !== null) {
                $builder = $builder
                    ->selectRaw($bucket->expression, $bucket->alias)
                    ->groupByRaw($bucket->expression);

                continue;
            }

            $field = $this->resolveField($key->field, $plan->dataset);
            $builder = $builder->select($field)->groupBy($field);
        }

        return $builder;
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>  $builder
     * @param  list<AggregateClause>  $aggregates
     * @return QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>
     */
    private function applyAggregates(QueryBuilder $builder, array $aggregates, TargetDataset $dataset): QueryBuilder
    {
        foreach ($aggregates as $agg) {
            $field = $agg->field !== null ? $this->resolveField($agg->field, $dataset) : null;

            // Numeric aggregates on a text-stored numeric column would otherwise be rejected
            // (avg/sum) or evaluated lexicographically (min/max), so cast the column to a number.
            if ($field !== null && $this->isNumericAggregate($agg->fn) && $this->needsNumericCast($field, $dataset)) {
                $builder = $builder->selectRaw(
                    sprintf('%s(%s::number)', $agg->fn->value, $field->value),
                    $agg->alias,
                );

                continue;
            }

            $builder = match ($agg->fn) {
                AggregateFn::Count => $builder->count($field, $agg->alias),
                AggregateFn::CountDistinct => $builder->countDistinct($this->requireField($field, AggregateFn::CountDistinct), $agg->alias),
                AggregateFn::Sum => $builder->sum($this->requireField($field, AggregateFn::Sum), $agg->alias),
                AggregateFn::Avg => $builder->avg($this->requireField($field, AggregateFn::Avg), $agg->alias),
                AggregateFn::Min => $builder->min($this->requireField($field, AggregateFn::Min), $agg->alias),
                AggregateFn::Max => $builder->max($this->requireField($field, AggregateFn::Max), $agg->alias),
            };
        }

        return $builder;
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>  $builder
     * @param  list<OrderClause>  $orderBy
     * @param  list<AggregateClause>  $aggregates
     * @param  array<string, BucketExpression>  $buckets
     * @return QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>
     */
    private function applyOrderBy(QueryBuilder $builder, array $orderBy, array $aggregates, array $buckets, TargetDataset $dataset): QueryBuilder
    {
        $aliasSet = [];
        foreach ($aggregates as $agg) {
            $aliasSet[$agg->alias] = true;
        }

        // Sorting on a NULLable column lets RDW push the NULLs to the top — fine for `ASC` but
        // hides actual top values for `DESC`. Apply `IS NOT NULL` once per column to make the
        // sort meaningful in both directions.
        $notNullApplied = [];

        foreach ($orderBy as $clause) {
            $direction = $clause->direction === OrderDirection::Desc ? SortDirection::Desc : SortDirection::Asc;

            if (isset($buckets[$clause->expr])) {
                $builder = $builder->orderByRaw($buckets[$clause->expr]->expression.' '.$direction->value);

                continue;
            }

            $field = $this->tryResolveField($clause->expr, $dataset);
            if ($field !== null) {
                if (! isset($notNullApplied[$clause->expr])) {
                    $builder = $builder->whereNotNull($field);
                    $notNullApplied[$clause->expr] = true;
                }

                // A text-stored numeric must sort by its numeric value, not lexicographically.
                $builder = $this->needsNumericCast($field, $dataset)
                    ? $builder->orderByRaw(sprintf('%s::number %s', $field->value, $direction->value))
                    : $builder->orderBy($field, $direction);

                continue;
            }

            if (! isset($aliasSet[$clause->expr])) {
                throw new InvalidArgumentException(sprintf(
                    'orderBy expression "%s" is neither a known field nor a declared aggregate alias.',
                    $clause->expr,
                ));
            }

            $builder = $builder->orderByRaw($clause->expr.' '.$direction->value);
        }

        return $builder;
    }

    private function needsNumericCast(BackedEnum $field, TargetDataset $dataset): bool
    {
        // RDW field enums are all string-backed, but `BackedEnum::$value` is typed `int|string`.
        // Coerce here so the storage-type lookup gets the string key it expects.
        return $this->storageTypes->needsNumericWrap($dataset, (string) $field->value);
    }

    private function isNumericAggregate(AggregateFn $fn): bool
    {
        return match ($fn) {
            AggregateFn::Sum, AggregateFn::Avg, AggregateFn::Min, AggregateFn::Max => true,
            default => false,
        };
    }

    /**
     * Guards the `whereRaw` interpolation against SoQL injection. `is_numeric` would let scientific
     * notation through (`"1e500"` → SoQL literal `INF`), and trims/locale forms could shift between
     * PHP versions, so we lock to a strict decimal grammar.
     */
    private function assertNumericLiteral(BackedEnum $field, string $raw): string
    {
        if (preg_match(self::NUMERIC_LITERAL_PATTERN, $raw) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Numeric comparison on field "%s" requires a numeric value, got "%s".',
                $field->name,
                $raw,
            ));
        }

        return $raw;
    }

    /**
     * Separator-insensitive substring predicate, since RDW free-text fields spell values with inconsistent spaces/hyphens.
     */
    private function normalisedContainsExpression(BackedEnum $field, string $value): string
    {
        $term = strtoupper(str_replace([' ', '-'], '', $value));
        $quoted = "'".str_replace("'", "''", $term)."'";

        return sprintf(
            "contains(replace(replace(%s, ' ', ''), '-', ''), %s)",
            $field->value,
            $quoted,
        );
    }

    private function resolveField(string $name, TargetDataset $dataset): BackedEnum
    {
        $field = $this->tryResolveField($name, $dataset);
        if ($field === null) {
            throw new InvalidArgumentException(sprintf('Unknown field "%s" for dataset %s.', $name, $dataset->value));
        }

        return $field;
    }

    private function tryResolveField(string $name, TargetDataset $dataset): ?BackedEnum
    {
        return FieldLookup::tryGet($dataset, $name);
    }

    private function requireField(?BackedEnum $field, AggregateFn $fn): BackedEnum
    {
        if ($field === null) {
            throw new InvalidArgumentException(sprintf('Aggregate %s requires a field.', $fn->value));
        }

        return $field;
    }
}
