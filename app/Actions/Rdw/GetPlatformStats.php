<?php

declare(strict_types=1);

namespace App\Actions\Rdw;

use App\Models\QueryRun;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use NiekNijland\RDW\Datasets\DatasetId;
use NiekNijland\RDW\Rdw;
use Throwable;

/**
 * Collects the platform-wide figures shown in the footer strip: the live RDW vehicle count,
 * the number of datasets the RDW package exposes, and the total number of answered questions.
 */
final readonly class GetPlatformStats
{
    /** RDW publishes daily, so the vehicle count's natural lifetime is a day (key also rotates at Amsterdam midnight). */
    private const int VEHICLES_TTL_SECONDS = 86_400;

    /** A failed lookup is cached briefly so page loads don't repeatedly block on a dead upstream. */
    private const int FAILURE_TTL_SECONDS = 600;

    public function __construct(
        private Rdw $rdw,
        private Repository $cache,
    ) {}

    /**
     * @return array{vehicles: int|null, datasets: int, queriesAnswered: int}
     */
    public function execute(): array
    {
        return [
            'vehicles' => $this->vehicleCount(),
            'datasets' => count(DatasetId::cases()),
            'queriesAnswered' => QueryRun::query()->count(),
        ];
    }

    /**
     * The cached count is an int sentinel: positive means a real figure, zero means the last
     * attempt failed (null can't be cached — a cache miss and a cached null are indistinguishable).
     */
    private function vehicleCount(): ?int
    {
        $key = sprintf(
            'platform-stats:vehicles:%s',
            CarbonImmutable::now('Europe/Amsterdam')->toDateString(),
        );

        $cached = $this->cache->get($key);
        if (is_int($cached)) {
            return $cached > 0 ? $cached : null;
        }

        try {
            $rows = $this->rdw->registeredVehicles()->count()->getProjection();
            $count = is_numeric($rows[0]['count'] ?? null) ? (int) $rows[0]['count'] : 0;
        } catch (Throwable) {
            $count = 0;
        }

        $this->cache->put(
            $key,
            $count,
            $count > 0 ? self::VEHICLES_TTL_SECONDS : self::FAILURE_TTL_SECONDS,
        );

        return $count > 0 ? $count : null;
    }
}
