<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Actions\Rdw\QueryExecutionException;
use Carbon\CarbonImmutable;
use Illuminate\Cache\NullStore;
use Illuminate\Cache\Repository;
use NiekNijland\RDW\Exceptions\RateLimitException;
use NiekNijland\RDW\Query\QueryBuilder;
use NiekNijland\RDW\Rdw;
use NiekNijland\RDW\Records\RegisteredVehicle;
use NiekNijland\RDW\Records\RegisteredVehicleFuel;
use Throwable;

/**
 * Orchestrates the execution of a single {@see Plan} against RDW: delegates query construction to
 * {@see QueryAssembler}, executes it (with retry + cache), and hands the raw rows to
 * {@see ResultNormalizer} to re-key into the public PascalCase shape.
 */
final readonly class PlanRunner
{
    /** Aggregate results turn over slowly — RDW publishes daily, so a 24h cache is the natural lifetime. */
    private const int AGGREGATE_TTL_SECONDS = 86_400;

    /** Row projections (sample lists, plate lookups) can churn during a session, so the TTL is short. */
    private const int ROW_TTL_SECONDS = 600;

    /** Socrata's default `$limit` is 1000; pages match it so server-side paging stays cheap. */
    private const int PROJECTION_PAGE_SIZE = 1000;

    /** Hard ceiling on rows pulled back for a single uncapped projection — guards memory + LLM cost. */
    private const int DEFAULT_MAX_PROJECTION_ROWS = 50_000;

    public function __construct(
        private Rdw $rdw,
        private QueryAssembler $assembler,
        private ResultNormalizer $normalizer,
        private Repository $cache = new Repository(new NullStore),
        private int $maxAttempts = 2,
        private int $retryBackoffMs = 250,
        private int $maxProjectionRows = self::DEFAULT_MAX_PROJECTION_ROWS,
    ) {}

    public function run(Plan $plan): RunnerResult
    {
        if ($plan->display === DisplayHint::Unsupported) {
            return new RunnerResult(rows: [], soql: [], url: '');
        }

        $buckets = $this->assembler->buildBucketsByField($plan->groupBy, $plan->dataset);
        $builder = $this->assembler->assemble($plan, $buckets);

        $soql = $builder->toSoqlParams();
        $url = $this->buildRequestUrl($soql, $plan->dataset);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->cache->remember(
            $this->cacheKey($soql, $plan->dataset),
            $this->cacheTtlSeconds($plan),
            fn (): array => $this->fetch($builder, $plan, $buckets, $soql, $url),
        );

        return new RunnerResult(rows: $rows, soql: $soql, url: $url);
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>  $builder
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

    /**
     * @param  QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>  $builder
     * @param  array<string, BucketExpression>  $buckets
     * @return list<array<string, mixed>>
     */
    private function execute(QueryBuilder $builder, Plan $plan, array $buckets): array
    {
        if ($plan->aggregates !== [] || $plan->groupBy !== []) {
            return $this->normalizer->fromProjection(
                $this->fetchProjectionRows($builder, $plan),
                $plan->aggregates,
                $buckets,
                $plan->dataset,
            );
        }

        return $this->normalizer->fromRecords($builder->get(), $plan->select, $plan->dataset);
    }

    /**
     * @param  QueryBuilder<RegisteredVehicle|RegisteredVehicleFuel>  $builder
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

    private function cacheTtlSeconds(Plan $plan): int
    {
        return $plan->aggregates !== [] || $plan->groupBy !== []
            ? self::AGGREGATE_TTL_SECONDS
            : self::ROW_TTL_SECONDS;
    }

    /**
     * Cache key rotates daily on Amsterdam-local midnight, matching the RDW publication cadence.
     *
     * @param  array<string, string>  $soql
     */
    private function cacheKey(array $soql, TargetDataset $dataset): string
    {
        ksort($soql);

        return sprintf(
            'rdw:%s:%s:%s',
            $dataset->datasetId()->value,
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
    private function buildRequestUrl(array $soql, TargetDataset $dataset): string
    {
        $base = rtrim($this->rdw->configuration()->baseUrl, '/');
        $datasetId = $dataset->datasetId()->value;
        $query = http_build_query($soql, '', '&', PHP_QUERY_RFC3986);

        return "{$base}/resource/{$datasetId}.json".($query !== '' ? "?{$query}" : '');
    }
}
