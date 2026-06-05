<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\QueryRun;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;
use Traversable;

/**
 * Aggregates query runs into per-day buckets (counts, cost, tokens, ratings) for the admin stats
 * page. One aggregation pipeline per window; results are cached briefly so refreshing the page
 * doesn't re-scan the collection.
 */
final readonly class GetAdminStats
{
    private const int CACHE_TTL_SECONDS = 300;

    /** Buckets follow the public-facing day boundary, like the platform stats cache key. */
    private const string TIMEZONE = 'Europe/Amsterdam';

    public function __construct(private Repository $cache)
    {
    }

    /**
     * @return array{
     *     perDay: list<array{date: string, queries: int, cost: float, promptTokens: int, completionTokens: int, up: int, down: int}>,
     *     totals: array{queries: int, cost: float, promptTokens: int, completionTokens: int, up: int, down: int, allTimeQueries: int},
     * }
     */
    public function execute(int $days = 30): array
    {
        $key = "admin-stats:{$days}";

        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            /** @var array{perDay: list<array{date: string, queries: int, cost: float, promptTokens: int, completionTokens: int, up: int, down: int}>, totals: array{queries: int, cost: float, promptTokens: int, completionTokens: int, up: int, down: int, allTimeQueries: int}} $cached */
            return $cached;
        }

        $start = CarbonImmutable::now(self::TIMEZONE)->subDays($days - 1)->startOfDay();
        $buckets = $this->aggregatePerDay($start);

        /** @var list<array{date: string, queries: int, cost: float, promptTokens: int, completionTokens: int, up: int, down: int}> $perDay */
        $perDay = [];
        /** @var array{queries: int, cost: float, promptTokens: int, completionTokens: int, up: int, down: int} $totals */
        $totals = ['queries' => 0, 'cost' => 0.0, 'promptTokens' => 0, 'completionTokens' => 0, 'up' => 0, 'down' => 0];

        // Emit every day in the window so charts show gaps as zero instead of skipping dates.
        for ($day = $start; $day->lessThanOrEqualTo(CarbonImmutable::now(self::TIMEZONE)); $day = $day->addDay()) {
            $date = $day->toDateString();
            $bucket = $buckets[$date] ?? ['queries' => 0, 'cost' => 0.0, 'promptTokens' => 0, 'completionTokens' => 0, 'up' => 0, 'down' => 0];

            $perDay[] = ['date' => $date, ...$bucket];

            $totals['queries'] += $bucket['queries'];
            $totals['cost'] += $bucket['cost'];
            $totals['promptTokens'] += $bucket['promptTokens'];
            $totals['completionTokens'] += $bucket['completionTokens'];
            $totals['up'] += $bucket['up'];
            $totals['down'] += $bucket['down'];
        }

        $allTimeQueries = QueryRun::query()->get()->count();

        $stats = [
            'perDay' => $perDay,
            'totals' => [...$totals, 'allTimeQueries' => $allTimeQueries],
        ];

        $this->cache->put($key, $stats, self::CACHE_TTL_SECONDS);

        return $stats;
    }

    /**
     * @return array<string, array{queries: int, cost: float, promptTokens: int, completionTokens: int, up: int, down: int}>
     */
    private function aggregatePerDay(CarbonImmutable $start): array
    {
        /** @var Connection $connection */
        $connection = QueryRun::query()->getConnection();

        /** @var Collection $collection */
        $collection = $connection->getCollection((new QueryRun())->getTable());

        /** @var Traversable<array-key, array<string, mixed>> $cursor */
        $cursor = $collection->aggregate([
            ['$match' => ['created_at' => ['$gte' => new UTCDateTime($start)]]],
            ['$group' => [
                '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at', 'timezone' => self::TIMEZONE]],
                'queries' => ['$sum' => 1],
                'cost' => ['$sum' => ['$ifNull' => ['$estimated_cost', 0]]],
                'promptTokens' => ['$sum' => ['$ifNull' => ['$prompt_tokens', 0]]],
                'completionTokens' => ['$sum' => ['$ifNull' => ['$completion_tokens', 0]]],
                'up' => ['$sum' => ['$cond' => [['$eq' => ['$rating', 'up']], 1, 0]]],
                'down' => ['$sum' => ['$cond' => [['$eq' => ['$rating', 'down']], 1, 0]]],
            ]],
        ], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);

        /** @var list<array<string, mixed>> $documents */
        $documents = iterator_to_array($cursor, false);

        $buckets = [];

        foreach ($documents as $bucket) {
            $buckets[(string) $bucket['_id']] = [
                'queries' => (int) $bucket['queries'],
                'cost' => round((float) $bucket['cost'], 6),
                'promptTokens' => (int) $bucket['promptTokens'],
                'completionTokens' => (int) $bucket['completionTokens'],
                'up' => (int) $bucket['up'],
                'down' => (int) $bucket['down'],
            ];
        }

        return $buckets;
    }
}
