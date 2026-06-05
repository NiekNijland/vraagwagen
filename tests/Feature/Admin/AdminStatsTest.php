<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\QueryRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminStatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->admin()->createOne());
    }

    public function test_buckets_runs_per_day_with_totals(): void
    {
        QueryRun::factory()->ratedUp()->createOne([
            'created_at' => now(),
            'estimated_cost' => 0.001,
            'prompt_tokens' => 100,
            'completion_tokens' => 10,
        ]);
        QueryRun::factory()->ratedDown()->createOne([
            'created_at' => now(),
            'estimated_cost' => 0.002,
            'prompt_tokens' => 200,
            'completion_tokens' => 20,
        ]);
        QueryRun::factory()->createOne([
            'created_at' => now()->subDays(2),
            'estimated_cost' => 0.004,
            'prompt_tokens' => 400,
            'completion_tokens' => 40,
        ]);
        // Outside the 7-day window; must not be counted in window totals.
        QueryRun::factory()->createOne(['created_at' => now()->subDays(30)]);

        $this->get(route('admin.stats.index', ['days' => 7]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/stats/index')
                ->where('days', 7)
                ->count('stats.perDay', 7)
                ->where('stats.totals.queries', 3)
                ->where('stats.totals.cost', 0.007)
                ->where('stats.totals.promptTokens', 700)
                ->where('stats.totals.completionTokens', 70)
                ->where('stats.totals.up', 1)
                ->where('stats.totals.down', 1)
                ->where('stats.totals.allTimeQueries', 4));
    }

    public function test_days_with_no_runs_are_zero_filled(): void
    {
        QueryRun::factory()->createOne(['created_at' => now()]);

        $this->get(route('admin.stats.index', ['days' => 7]))
            ->assertInertia(fn (Assert $page) => $page
                ->count('stats.perDay', 7)
                ->where('stats.perDay.0.queries', 0)
                ->where('stats.perDay.6.queries', 1));
    }

    public function test_rejects_unsupported_windows(): void
    {
        $this->get(route('admin.stats.index', ['days' => 9999]))
            ->assertSessionHasErrors('days');
    }
}
