<?php

declare(strict_types=1);

namespace App\Services\RateLimit;

use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Effective rate-limit values: an admin-set override from the settings collection wins over the
 * config (env) default. Overrides are read as one cached array so the limiter closures, which run
 * on every throttled request, don't hit MongoDB.
 */
class RateLimitSettings
{
    private const string CACHE_KEY = 'rate-limit:overrides';

    private const int CACHE_TTL_SECONDS = 300;

    public const array KEYS = ['per_minute', 'per_day_ip', 'per_day_global', 'feedback_per_minute'];

    public function __construct(private readonly Repository $cache)
    {
    }

    public function perMinute(): int
    {
        return $this->effective('per_minute');
    }

    public function perDayIp(): int
    {
        return $this->effective('per_day_ip');
    }

    public function perDayGlobal(): int
    {
        return $this->effective('per_day_global');
    }

    public function feedbackPerMinute(): int
    {
        return $this->effective('feedback_per_minute');
    }

    /**
     * @return array<string, array{value: int, overridden: bool, default: int}>
     */
    public function all(): array
    {
        $overrides = $this->overrides();
        $values = [];

        foreach (self::KEYS as $key) {
            $default = (int) config("vraagwagen.rate_limit.{$key}");

            $values[$key] = [
                'value' => $overrides[$key] ?? $default,
                'overridden' => array_key_exists($key, $overrides),
                'default' => $default,
            ];
        }

        return $values;
    }

    /**
     * @param array<string, int> $values
     */
    public function update(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! in_array($key, self::KEYS, true)) {
                continue;
            }

            Setting::query()->updateOrCreate(
                ['key' => "rate_limit.{$key}"],
                ['value' => $value],
            );
        }

        $this->cache->forget(self::CACHE_KEY);
    }

    public function clearOverride(string $key): void
    {
        Setting::query()->where('key', "rate_limit.{$key}")->delete();

        $this->cache->forget(self::CACHE_KEY);
    }

    private function effective(string $key): int
    {
        return $this->overrides()[$key] ?? (int) config("vraagwagen.rate_limit.{$key}");
    }

    /**
     * @return array<string, int>
     */
    private function overrides(): array
    {
        $cached = $this->cache->get(self::CACHE_KEY);

        if (is_array($cached)) {
            /** @var array<string, int> $cached */
            return $cached;
        }

        /** @var Collection<int, Setting> $settings */
        $settings = Setting::query()
            ->where('key', 'like', 'rate_limit.%')
            ->get();

        $overrides = $settings
            ->mapWithKeys(static fn (Setting $setting): array => [
                substr($setting->key, strlen('rate_limit.')) => (int) $setting->value,
            ])
            ->all();

        $this->cache->put(self::CACHE_KEY, $overrides, self::CACHE_TTL_SECONDS);

        return $overrides;
    }
}
