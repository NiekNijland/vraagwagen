<?php

declare(strict_types=1);

namespace Tests\Unit\Services\RateLimit;

use App\Services\RateLimit\RateLimitInspector;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class RateLimitInspectorTest extends TestCase
{
    private RateLimitInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inspector = app(RateLimitInspector::class);
    }

    public function test_global_usage_reads_the_throttle_middleware_key(): void
    {
        config()->set('vraagwagen.rate_limit.per_day_global', 50);

        // ThrottleRequests stores named-limiter hits under md5($limiterName . $limit->key).
        RateLimiter::hit(md5('rdw-query' . 'rdw-query:global'), 86_400);
        RateLimiter::hit(md5('rdw-query' . 'rdw-query:global'), 86_400);

        $usage = $this->inspector->globalUsage();

        self::assertSame(2, $usage['used']);
        self::assertSame(50, $usage['limit']);
        self::assertSame(48, $usage['remaining']);
        self::assertGreaterThan(0, $usage['resetsInSeconds']);
    }

    public function test_reset_global_clears_the_counter(): void
    {
        RateLimiter::hit(md5('rdw-query' . 'rdw-query:global'), 86_400);

        $this->inspector->resetGlobal();

        self::assertSame(0, $this->inspector->globalUsage()['used']);
    }

    public function test_ip_usage_covers_minute_day_and_feedback_keys(): void
    {
        config()->set('vraagwagen.rate_limit.per_minute', 10);
        config()->set('vraagwagen.rate_limit.per_day_ip', 25);
        config()->set('vraagwagen.rate_limit.feedback_per_minute', 30);

        $ip = '10.0.0.1';
        RateLimiter::hit(md5('rdw-query' . $ip), 60);
        RateLimiter::hit(md5('rdw-query' . 'rdw-query:ip:' . $ip), 86_400);
        RateLimiter::hit(md5('rdw-query' . 'rdw-query:ip:' . $ip), 86_400);
        RateLimiter::hit(md5('rdw-feedback' . $ip), 60);

        $usage = $this->inspector->ipUsage($ip);

        self::assertSame(1, $usage['perMinute']['used']);
        self::assertSame(2, $usage['perDay']['used']);
        self::assertSame(23, $usage['perDay']['remaining']);
        self::assertSame(1, $usage['feedbackPerMinute']['used']);
    }

    public function test_reset_ip_clears_all_ip_scoped_counters(): void
    {
        $ip = '10.0.0.1';
        RateLimiter::hit(md5('rdw-query' . $ip), 60);
        RateLimiter::hit(md5('rdw-query' . 'rdw-query:ip:' . $ip), 86_400);
        RateLimiter::hit(md5('rdw-feedback' . $ip), 60);

        $this->inspector->resetIp($ip);

        $usage = $this->inspector->ipUsage($ip);

        self::assertSame(0, $usage['perMinute']['used']);
        self::assertSame(0, $usage['perDay']['used']);
        self::assertSame(0, $usage['feedbackPerMinute']['used']);
    }
}
