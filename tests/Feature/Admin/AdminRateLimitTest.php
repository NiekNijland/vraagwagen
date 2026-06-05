<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Rdw\RunNaturalLanguageQuery;
use App\Models\Setting;
use App\Models\User;
use App\Services\QueryPlan\DisplayHint;
use App\Services\QueryPlan\Plan;
use App\Services\QueryPlan\QueryResult;
use App\Services\QueryPlan\TargetDataset;
use App\Services\QueryPlan\TokenUsage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

final class AdminRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_index_shows_limits_and_global_usage(): void
    {
        config()->set('vraagwagen.rate_limit.per_day_global', 50);

        $this->actingAs(User::factory()->admin()->createOne());

        $this->get(route('admin.rate-limits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/rate-limits/index')
                ->where('limits.per_day_global.value', 50)
                ->where('limits.per_day_global.overridden', false)
                ->where('globalUsage.used', 0)
                ->where('globalUsage.limit', 50)
                ->where('ipUsage', null));
    }

    public function test_index_shows_usage_for_a_requested_ip(): void
    {
        $this->actingAs(User::factory()->admin()->createOne());

        $this->get(route('admin.rate-limits.index', ['ip' => '10.0.0.9']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ip', '10.0.0.9')
                ->has('ipUsage.perMinute')
                ->has('ipUsage.perDay')
                ->has('ipUsage.feedbackPerMinute'));
    }

    public function test_update_persists_overrides_and_changes_the_effective_limit(): void
    {
        $this->mockQueryAction();
        config()->set('vraagwagen.rate_limit.per_minute', 10);

        $this->actingAs(User::factory()->admin()->createOne());

        $this->patch(route('admin.rate-limits.update'), [
            'per_minute' => 2,
            'per_day_ip' => 25,
            'per_day_global' => 50,
            'feedback_per_minute' => 30,
        ])->assertRedirect(route('admin.rate-limits.index'));

        $this->assertSame(4, Setting::query()->count());

        // The lowered per-minute override now gates the public query endpoint.
        $this->postJson(route('rdw.query.run'), ['prompt' => 'count vehicles'])->assertOk();
        $this->postJson(route('rdw.query.run'), ['prompt' => 'count vehicles'])->assertOk();
        $this->postJson(route('rdw.query.run'), ['prompt' => 'count vehicles'])->assertStatus(429);
    }

    public function test_update_rejects_invalid_values(): void
    {
        $this->actingAs(User::factory()->admin()->createOne());

        $this->from(route('admin.rate-limits.index'))
            ->patch(route('admin.rate-limits.update'), [
                'per_minute' => 0,
                'per_day_ip' => 'lots',
                'per_day_global' => 50,
            ])
            ->assertSessionHasErrors(['per_minute', 'per_day_ip', 'feedback_per_minute']);
    }

    public function test_reset_global_clears_the_live_counter(): void
    {
        $this->mockQueryAction();
        config()->set('vraagwagen.rate_limit.per_day_global', 50);

        $this->postJson(route('rdw.query.run'), ['prompt' => 'count vehicles'])->assertOk();

        $this->actingAs(User::factory()->admin()->createOne());

        $this->get(route('admin.rate-limits.index'))
            ->assertInertia(fn (Assert $page) => $page->where('globalUsage.used', 1));

        $this->post(route('admin.rate-limits.reset'), ['scope' => 'global']);

        $this->get(route('admin.rate-limits.index'))
            ->assertInertia(fn (Assert $page) => $page->where('globalUsage.used', 0));
    }

    public function test_reset_ip_clears_that_ips_counters(): void
    {
        $this->mockQueryAction();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
            ->postJson(route('rdw.query.run'), ['prompt' => 'count vehicles'])
            ->assertOk();

        $this->actingAs(User::factory()->admin()->createOne());

        $this->get(route('admin.rate-limits.index', ['ip' => '10.0.0.7']))
            ->assertInertia(fn (Assert $page) => $page->where('ipUsage.perDay.used', 1));

        $this->post(route('admin.rate-limits.reset'), ['scope' => 'ip', 'ip' => '10.0.0.7']);

        $this->get(route('admin.rate-limits.index', ['ip' => '10.0.0.7']))
            ->assertInertia(fn (Assert $page) => $page->where('ipUsage.perDay.used', 0));
    }

    public function test_reset_requires_an_ip_for_the_ip_scope(): void
    {
        $this->actingAs(User::factory()->admin()->createOne());

        $this->from(route('admin.rate-limits.index'))
            ->post(route('admin.rate-limits.reset'), ['scope' => 'ip'])
            ->assertSessionHasErrors('ip');
    }

    private function mockQueryAction(): void
    {
        $mock = Mockery::mock(RunNaturalLanguageQuery::class);
        // @phpstan-ignore method.notFound (Mockery fluent API is not statically resolvable)
        $mock->shouldReceive('execute')->andReturn(new QueryResult(
            plan: new Plan(
                dataset: TargetDataset::RegisteredVehicles,
                where: [],
                select: [],
                groupBy: [],
                aggregates: [],
                orderBy: [],
                limit: null,
                display: DisplayHint::Count,
                explanation: '',
            ),
            rows: [],
            soql: [],
            url: 'https://opendata.rdw.nl/resource/test.json',
            model: 'fake',
            tokens: new TokenUsage(prompt: 0, completion: 0, cacheRead: 0, thought: 0),
            estimatedCost: null,
        ));
        $this->app->instance(RunNaturalLanguageQuery::class, $mock);
    }
}
