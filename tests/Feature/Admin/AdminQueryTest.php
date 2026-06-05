<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\QueryRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->admin()->createOne());
    }

    public function test_lists_runs_newest_first(): void
    {
        QueryRun::factory()->createOne(['prompt' => 'older', 'created_at' => now()->subDay()]);
        QueryRun::factory()->createOne(['prompt' => 'newer', 'created_at' => now()]);

        $this->get(route('admin.queries.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/queries/index')
                ->where('runs.data.0.prompt', 'newer')
                ->where('runs.data.1.prompt', 'older')
                ->where('runs.total', 2));
    }

    public function test_paginates_runs(): void
    {
        QueryRun::factory()->count(26)->create();

        $this->get(route('admin.queries.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->count('runs.data', 25)
                ->where('runs.total', 26));
    }

    public function test_filters_by_search_rating_and_locale(): void
    {
        QueryRun::factory()->ratedUp()->createOne(['prompt' => 'hoeveel teslas', 'locale' => 'nl']);
        QueryRun::factory()->ratedDown()->createOne(['prompt' => 'how many porsches', 'locale' => 'en']);
        QueryRun::factory()->createOne(['prompt' => 'unrated tesla question', 'locale' => 'en']);

        $this->get(route('admin.queries.index', ['search' => 'tesla']))
            ->assertInertia(fn (Assert $page) => $page->where('runs.total', 2));

        $this->get(route('admin.queries.index', ['rating' => 'down']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('runs.total', 1)
                ->where('runs.data.0.prompt', 'how many porsches'));

        $this->get(route('admin.queries.index', ['locale' => 'nl']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('runs.total', 1)
                ->where('runs.data.0.prompt', 'hoeveel teslas'));
    }

    public function test_filters_by_date_range(): void
    {
        QueryRun::factory()->createOne(['prompt' => 'old', 'created_at' => now()->subDays(10)]);
        QueryRun::factory()->createOne(['prompt' => 'recent', 'created_at' => now()->subDay()]);

        $this->get(route('admin.queries.index', ['from' => now()->subDays(2)->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('runs.total', 1)
                ->where('runs.data.0.prompt', 'recent'));

        $this->get(route('admin.queries.index', ['to' => now()->subDays(5)->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('runs.total', 1)
                ->where('runs.data.0.prompt', 'old'));
    }

    public function test_rejects_invalid_filters(): void
    {
        $this->get(route('admin.queries.index', ['rating' => 'sideways']))
            ->assertSessionHasErrors('rating');
    }

    public function test_shows_full_run_detail(): void
    {
        $run = QueryRun::factory()->ratedDown()->createOne([
            'prompt' => 'how many teslas',
            'comment' => 'Wrong count',
            'model' => 'gpt-4.1-mini',
            'prompt_tokens' => 100,
            'completion_tokens' => 20,
            'estimated_cost' => 0.0001,
        ]);

        $this->get(route('admin.queries.show', $run->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/queries/show')
                ->where('run.prompt', 'how many teslas')
                ->where('run.rating', 'down')
                ->where('run.comment', 'Wrong count')
                ->where('run.model', 'gpt-4.1-mini')
                ->where('run.promptTokens', 100)
                ->where('run.completionTokens', 20)
                ->where('run.rowCount', 1)
                ->has('run.plan')
                ->has('run.soql'));
    }

    public function test_detail_returns_404_for_unknown_id(): void
    {
        $this->get(route('admin.queries.show', '662b1234567890abcdef1234'))
            ->assertNotFound();
    }
}
