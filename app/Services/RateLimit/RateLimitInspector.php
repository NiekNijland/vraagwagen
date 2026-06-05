<?php

declare(strict_types=1);

namespace App\Services\RateLimit;

use Illuminate\Cache\RateLimiter;

/**
 * Reads and resets throttle counters by reconstructing the cache keys ThrottleRequests uses for
 * named limiters: md5($limiterName . $limit->key). Per-IP keys are not enumerable in the cache
 * store, so IP usage is looked up for an explicitly given address; only the global key is known
 * upfront.
 */
class RateLimitInspector
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly RateLimitSettings $settings,
    ) {
    }

    /**
     * @return array{used: int, limit: int, remaining: int, resetsInSeconds: int}
     */
    public function globalUsage(): array
    {
        return $this->usage(self::globalKey(), $this->settings->perDayGlobal());
    }

    /**
     * @return array{
     *     perMinute: array{used: int, limit: int, remaining: int, resetsInSeconds: int},
     *     perDay: array{used: int, limit: int, remaining: int, resetsInSeconds: int},
     *     feedbackPerMinute: array{used: int, limit: int, remaining: int, resetsInSeconds: int},
     * }
     */
    public function ipUsage(string $ip): array
    {
        return [
            'perMinute' => $this->usage(self::ipMinuteKey($ip), $this->settings->perMinute()),
            'perDay' => $this->usage(self::ipDayKey($ip), $this->settings->perDayIp()),
            'feedbackPerMinute' => $this->usage(self::feedbackKey($ip), $this->settings->feedbackPerMinute()),
        ];
    }

    public function resetGlobal(): void
    {
        $this->limiter->clear(self::globalKey());
    }

    public function resetIp(string $ip): void
    {
        $this->limiter->clear(self::ipMinuteKey($ip));
        $this->limiter->clear(self::ipDayKey($ip));
        $this->limiter->clear(self::feedbackKey($ip));
    }

    /**
     * @return array{used: int, limit: int, remaining: int, resetsInSeconds: int}
     */
    private function usage(string $key, int $limit): array
    {
        $used = (int) $this->limiter->attempts($key);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
            'resetsInSeconds' => $used > 0 ? $this->limiter->availableIn($key) : 0,
        ];
    }

    private static function globalKey(): string
    {
        return md5('rdw-query' . 'rdw-query:global');
    }

    private static function ipMinuteKey(string $ip): string
    {
        return md5('rdw-query' . $ip);
    }

    private static function ipDayKey(string $ip): string
    {
        return md5('rdw-query' . 'rdw-query:ip:' . $ip);
    }

    private static function feedbackKey(string $ip): string
    {
        return md5('rdw-feedback' . $ip);
    }
}
