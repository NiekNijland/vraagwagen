<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Actions\Rdw\QueryExecutionException;
use Carbon\CarbonImmutable;
use Illuminate\Cache\NullStore;
use Illuminate\Cache\Repository;
use InvalidArgumentException;
use NiekNijland\RDW\Datasets\DatasetId;
use NiekNijland\RDW\Exceptions\RateLimitException;
use NiekNijland\RDW\Fields\RegisteredVehicleField;
use NiekNijland\RDW\Query\QueryBuilder;
use NiekNijland\RDW\Query\SortDirection;
use NiekNijland\RDW\Rdw;
use NiekNijland\RDW\Records\RegisteredVehicle;
use NiekNijland\RDW\Schema\CastType;
use NiekNijland\RDW\Schema\DatasetSchema;
use Throwable;

final readonly class PlanRunner
{
    // Heavy aggregate scans are low-cardinality; the date-scoped cache key rolls over with RDW's daily refresh.
    private const int AGGREGATE_TTL_SECONDS = 86_400;

    // Row lookups are freshness-sensitive (APK / ownership), so cached only briefly.
    private const int ROW_TTL_SECONDS = 600;

    // Socrata caps a request at 1000 rows; limit-less breakdowns page at this size.
    private const int PROJECTION_PAGE_SIZE = 1000;

    // Hard ceiling so a high-cardinality groupBy can't page an unbounded tail.
    private const int DEFAULT_MAX_PROJECTION_ROWS = 50_000;

    public function __construct(
        private Rdw $rdw,
        private Repository $cache = new Repository(new NullStore),
        private int $maxAttempts = 2,
        private int $retryBackoffMs = 250,
        private int $maxProjectionRows = self::DEFAULT_MAX_PROJECTION_ROWS,
    ) {}

    public function run(Plan $plan): RunnerResult
    {
        // A refusal plan has no SoQL to send; skip the dataset call so it never bills against the RDW rate limit.
        if ($plan->display === DisplayHint::Unsupported) {
            return new RunnerResult(rows: [], soql: [], url: '');
        }

        $buckets = $this->buildBucketsByField($plan->groupBy);

        $builder = $this->rdw->registeredVehicles();
        $builder = $this->applyWhere($builder, $plan->where);
        $builder = $this->applySelectAndGroupBy($builder, $plan, $buckets);
        $builder = $this->applyAggregates($builder, $plan->aggregates);
        $builder = $this->applyOrderBy($builder, $plan->orderBy, $plan->aggregates, $buckets);

        if ($plan->limit !== null) {
            $builder = $builder->limit($plan->limit);
        }

        $soql = $builder->toSoqlParams();
        $url = $this->buildRequestUrl($soql);

        // Prompts compiling to the same SoQL share one upstream call; only a successful fetch is cached.
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->cache->remember(
            $this->cacheKey($soql),
            $this->cacheTtlSeconds($plan),
            fn (): array => $this->fetch($builder, $plan, $buckets, $soql, $url),
        );

        return new RunnerResult(rows: $rows, soql: $soql, url: $url);
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle>  $builder
     * @param  array<string, BucketExpression>  $buckets
     * @param  array<string, string>  $soql
     * @return list<array<string, mixed>>
     */
    private function fetch(QueryBuilder $builder, Plan $plan, array $buckets, array $soql, string $url): array
    {
        $attempt = 1;
        while (true) {
            try {
                return $this->execute($builder, $plan, $buckets);
            } catch (RateLimitException $e) {
                // The controller maps this to a 429; pass through without wrapping or retrying.
                throw $e;
            } catch (Throwable $e) {
                if ($attempt < $this->maxAttempts && QueryExecutionException::isTransientFailure($e)) {
                    $this->backoff();
                    $attempt++;

                    continue;
                }

                throw new QueryExecutionException($plan, $soql, $url, $e);
            }
        }
    }

    private function cacheTtlSeconds(Plan $plan): int
    {
        return $plan->aggregates !== [] || $plan->groupBy !== []
            ? self::AGGREGATE_TTL_SECONDS
            : self::ROW_TTL_SECONDS;
    }

    /**
     * @param  array<string, string>  $soql
     */
    private function cacheKey(array $soql): string
    {
        ksort($soql);

        return sprintf(
            'rdw:%s:%s:%s',
            DatasetId::RegisteredVehicles->value,
            CarbonImmutable::now('Europe/Amsterdam')->toDateString(),
            sha1(json_encode($soql, JSON_THROW_ON_ERROR)),
        );
    }

    private function backoff(): void
    {
        if ($this->retryBackoffMs > 0) {
            usleep($this->retryBackoffMs * 1000);
        }
    }

    /**
     * @param  array<string, string>  $soql
     */
    private function buildRequestUrl(array $soql): string
    {
        $base = rtrim($this->rdw->configuration()->baseUrl, '/');
        $datasetId = DatasetId::RegisteredVehicles->value;
        $query = http_build_query($soql, '', '&', PHP_QUERY_RFC3986);

        return "{$base}/resource/{$datasetId}.json".($query !== '' ? "?{$query}" : '');
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle>  $builder
     * @param  list<WhereClause>  $clauses
     * @return QueryBuilder<RegisteredVehicle>
     */
    private function applyWhere(QueryBuilder $builder, array $clauses): QueryBuilder
    {
        foreach ($clauses as $clause) {
            $field = $this->resolveField($clause->field);

            $builder = match ($clause->op) {
                WhereOp::Equals => $builder->where($field, $this->castValue($field, $clause->value), '='),
                WhereOp::NotEquals => $builder->where($field, $this->castValue($field, $clause->value), '!='),
                WhereOp::GreaterThan => $builder->where($field, $this->castValue($field, $clause->value), '>'),
                WhereOp::GreaterThanOrEqual => $builder->where($field, $this->castValue($field, $clause->value), '>='),
                WhereOp::LessThan => $builder->where($field, $this->castValue($field, $clause->value), '<'),
                WhereOp::LessThanOrEqual => $builder->where($field, $this->castValue($field, $clause->value), '<='),
                WhereOp::Contains => $builder->whereRaw($this->normalisedContainsExpression($field, $clause->value)),
                WhereOp::StartsWith => $builder->whereStartsWith($field, $clause->value),
            };
        }

        return $builder;
    }

    /**
     * Separator-insensitive substring predicate, since RDW free-text fields spell values with inconsistent spaces/hyphens.
     */
    private function normalisedContainsExpression(RegisteredVehicleField $field, string $value): string
    {
        $term = strtoupper(str_replace([' ', '-'], '', $value));
        $quoted = "'".str_replace("'", "''", $term)."'";

        return sprintf(
            "contains(replace(replace(%s, ' ', ''), '-', ''), %s)",
            $field->value,
            $quoted,
        );
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle>  $builder
     * @param  array<string, BucketExpression>  $buckets
     * @return QueryBuilder<RegisteredVehicle>
     */
    private function applySelectAndGroupBy(QueryBuilder $builder, Plan $plan, array $buckets): QueryBuilder
    {
        foreach ($plan->select as $name) {
            $builder = $builder->select($this->resolveField($name));
        }

        foreach ($plan->groupBy as $key) {
            $bucket = $buckets[$key->field] ?? null;
            if ($bucket !== null) {
                $builder = $builder
                    ->selectRaw($bucket->expression, $bucket->alias)
                    ->groupByRaw($bucket->expression);

                continue;
            }

            $field = $this->resolveField($key->field);
            $builder = $builder->select($field)->groupBy($field);
        }

        return $builder;
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle>  $builder
     * @param  list<AggregateClause>  $aggregates
     * @return QueryBuilder<RegisteredVehicle>
     */
    private function applyAggregates(QueryBuilder $builder, array $aggregates): QueryBuilder
    {
        foreach ($aggregates as $agg) {
            $field = $agg->field !== null ? $this->resolveField($agg->field) : null;

            $builder = match ($agg->fn) {
                AggregateFn::Count => $builder->count($field, $agg->alias),
                AggregateFn::Sum => $builder->sum($this->requireField($field, AggregateFn::Sum), $agg->alias),
                AggregateFn::Avg => $builder->avg($this->requireField($field, AggregateFn::Avg), $agg->alias),
                AggregateFn::Min => $builder->min($this->requireField($field, AggregateFn::Min), $agg->alias),
                AggregateFn::Max => $builder->max($this->requireField($field, AggregateFn::Max), $agg->alias),
            };
        }

        return $builder;
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle>  $builder
     * @param  list<OrderClause>  $orderBy
     * @param  list<AggregateClause>  $aggregates
     * @param  array<string, BucketExpression>  $buckets
     * @return QueryBuilder<RegisteredVehicle>
     */
    private function applyOrderBy(QueryBuilder $builder, array $orderBy, array $aggregates, array $buckets): QueryBuilder
    {
        $aliasSet = [];
        foreach ($aggregates as $agg) {
            $aliasSet[$agg->alias] = true;
        }

        // Socrata sorts NULLs first on DESC, so force the sort column non-null; dedupe per field.
        $notNullApplied = [];

        foreach ($orderBy as $clause) {
            $direction = $clause->direction === OrderDirection::Desc ? SortDirection::Desc : SortDirection::Asc;

            if (isset($buckets[$clause->expr])) {
                $builder = $builder->orderByRaw($buckets[$clause->expr]->expression.' '.$direction->value);

                continue;
            }

            $field = $this->tryResolveField($clause->expr);
            if ($field !== null) {
                if (! isset($notNullApplied[$clause->expr])) {
                    $builder = $builder->whereNotNull($field);
                    $notNullApplied[$clause->expr] = true;
                }
                $builder = $builder->orderBy($field, $direction);

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

    /**
     * @param  QueryBuilder<RegisteredVehicle>  $builder
     * @param  array<string, BucketExpression>  $buckets
     * @return list<array<string, mixed>>
     */
    private function execute(QueryBuilder $builder, Plan $plan, array $buckets): array
    {
        if ($plan->aggregates !== [] || $plan->groupBy !== []) {
            return $this->normaliseProjectionRows($this->fetchProjectionRows($builder, $plan), $plan->aggregates, $buckets);
        }

        $records = $builder->get();

        return array_map(fn (object $r): array => $this->recordToArray($r, $plan->select), $records);
    }

    /**
     * Pages a limit-less projection until a short page (so a breakdown returns every bucket, not just Socrata's first 1000).
     *
     * @param  QueryBuilder<RegisteredVehicle>  $builder
     * @return list<array<string, mixed>>
     */
    private function fetchProjectionRows(QueryBuilder $builder, Plan $plan): array
    {
        if ($plan->limit !== null) {
            return $builder->getProjection();
        }

        $rows = [];
        $offset = 0;
        do {
            $page = $builder->limit(self::PROJECTION_PAGE_SIZE)->offset($offset)->getProjection();
            $rows = array_merge($rows, $page);
            $offset += self::PROJECTION_PAGE_SIZE;
        } while (count($page) === self::PROJECTION_PAGE_SIZE && count($rows) < $this->maxProjectionRows);

        return $rows;
    }

    /**
     * @param  list<string>  $select
     * @return array<string, mixed>
     */
    private function recordToArray(object $record, array $select): array
    {
        $vars = get_object_vars($record);
        $schema = $this->schema();

        if ($select === []) {
            $out = [];
            foreach ($vars as $key => $value) {
                $enumCase = $this->propertyToEnumCase($schema, $key) ?? $key;
                $out[$enumCase] = $this->normaliseValue($value);
            }

            return $out;
        }

        $out = [];
        foreach ($select as $enumCase) {
            $descriptor = $schema->byEnumCase[$enumCase] ?? null;
            if ($descriptor === null) {
                continue;
            }
            if (! array_key_exists($descriptor->propertyName, $vars)) {
                continue;
            }
            $out[$enumCase] = $this->normaliseValue($vars[$descriptor->propertyName]);
        }

        return $out;
    }

    /**
     * Rewrites Dutch snake_case rdwKey row keys to their PascalCase enum case; aliases pass through.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<AggregateClause>  $aggregates
     * @param  array<string, BucketExpression>  $buckets
     * @return list<array<string, mixed>>
     */
    private function normaliseProjectionRows(array $rows, array $aggregates, array $buckets): array
    {
        $schema = $this->schema();
        $passThrough = [];
        foreach ($aggregates as $agg) {
            $passThrough[$agg->alias] = true;
        }
        foreach ($buckets as $bucket) {
            $passThrough[$bucket->alias] = true;
        }

        $out = [];
        foreach ($rows as $row) {
            $normalised = [];
            foreach ($row as $key => $value) {
                $renamed = isset($passThrough[$key])
                    ? $key
                    : ($schema->byRdwKey[$key]->enumCase ?? $key);
                $normalised[$renamed] = $this->normaliseValue($value);
            }
            $out[] = $normalised;
        }

        return $out;
    }

    /**
     * @param  list<GroupKey>  $groupBy
     * @return array<string, BucketExpression>
     */
    private function buildBucketsByField(array $groupBy): array
    {
        $schema = $this->schema();
        $out = [];

        foreach ($groupBy as $key) {
            if ($key->bucket === Bucket::None) {
                continue;
            }

            $descriptor = $schema->byEnumCase[$key->field] ?? null;
            if ($descriptor === null) {
                throw new InvalidArgumentException(sprintf('Unknown RegisteredVehicleField "%s".', $key->field));
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

    private function normaliseValue(mixed $value): mixed
    {
        return $value instanceof CarbonImmutable ? $value->toDateString() : $value;
    }

    private function propertyToEnumCase(DatasetSchema $schema, string $propertyName): ?string
    {
        foreach ($schema->byEnumCase as $enumCase => $descriptor) {
            if ($descriptor->propertyName === $propertyName) {
                return $enumCase;
            }
        }

        return null;
    }

    private function resolveField(string $name): RegisteredVehicleField
    {
        $field = $this->tryResolveField($name);
        if ($field === null) {
            throw new InvalidArgumentException(sprintf('Unknown RegisteredVehicleField "%s".', $name));
        }

        return $field;
    }

    private function tryResolveField(string $name): ?RegisteredVehicleField
    {
        return RegisteredVehicleFieldLookup::tryGet($name);
    }

    private function requireField(?RegisteredVehicleField $field, AggregateFn $fn): RegisteredVehicleField
    {
        if ($field === null) {
            throw new InvalidArgumentException(sprintf('Aggregate %s requires a field.', $fn->value));
        }

        return $field;
    }

    private function castValue(RegisteredVehicleField $field, string $raw): mixed
    {
        // Plates are stored uppercase without separators; normalise so the case-sensitive match works.
        if ($field === RegisteredVehicleField::LicensePlate) {
            return PlateNormaliser::normalise($raw);
        }

        $descriptor = $this->schema()->byEnumCase[$field->name] ?? null;
        if ($descriptor === null) {
            return $raw;
        }

        return match ($descriptor->cast) {
            CastType::Boolean => in_array(strtolower($raw), ['true', '1', 'ja', 'yes'], true),
            CastType::Integer => (int) $raw,
            CastType::Decimal => is_numeric($raw) ? (float) $raw : $raw,
            CastType::CalendarDate, CastType::NumericDate => CarbonImmutable::parse($raw, 'UTC'),
            default => $raw,
        };
    }

    private function schema(): DatasetSchema
    {
        return $this->rdw->schemas()->get(DatasetId::RegisteredVehicles);
    }
}
