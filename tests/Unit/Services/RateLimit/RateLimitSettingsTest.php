<?php

declare(strict_types=1);

namespace Tests\Unit\Services\RateLimit;

use App\Models\Setting;
use App\Services\RateLimit\RateLimitSettings;
use Tests\TestCase;

final class RateLimitSettingsTest extends TestCase
{
    private RateLimitSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(RateLimitSettings::class);
    }

    public function test_falls_back_to_config_defaults_without_overrides(): void
    {
        config()->set('vraagwagen.rate_limit.per_minute', 12);
        config()->set('vraagwagen.rate_limit.per_day_global', 99);

        $this->assertSame(12, $this->settings->perMinute());
        $this->assertSame(99, $this->settings->perDayGlobal());
    }

    public function test_override_wins_over_config_default(): void
    {
        config()->set('vraagwagen.rate_limit.per_minute', 12);

        $this->settings->update(['per_minute' => 3]);

        $this->assertSame(3, $this->settings->perMinute());
        $this->assertSame('rate_limit.per_minute', Setting::query()->firstOrFail()->key);
    }

    public function test_update_invalidates_the_cached_overrides(): void
    {
        // Prime the cache with "no overrides".
        $this->assertSame((int) config('vraagwagen.rate_limit.per_day_ip'), $this->settings->perDayIp());

        $this->settings->update(['per_day_ip' => 5]);

        $this->assertSame(5, $this->settings->perDayIp());
    }

    public function test_update_ignores_unknown_keys(): void
    {
        $this->settings->update(['nonsense' => 1]);

        $this->assertSame(0, Setting::query()->count());
    }

    public function test_all_reports_value_source_and_default(): void
    {
        config()->set('vraagwagen.rate_limit.per_minute', 10);

        $this->settings->update(['per_minute' => 4]);

        $all = $this->settings->all();

        $this->assertSame(['value' => 4, 'overridden' => true, 'default' => 10], $all['per_minute']);
        $this->assertFalse($all['per_day_global']['overridden']);
    }

    public function test_clear_override_restores_the_default(): void
    {
        config()->set('vraagwagen.rate_limit.per_minute', 10);
        $this->settings->update(['per_minute' => 4]);

        $this->settings->clearOverride('per_minute');

        $this->assertSame(10, $this->settings->perMinute());
        $this->assertSame(0, Setting::query()->count());
    }
}
