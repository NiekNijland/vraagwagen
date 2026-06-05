<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\QueryRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminFeedbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->admin()->createOne());
    }

    public function test_lists_only_rated_runs(): void
    {
        QueryRun::factory()->ratedUp()->createOne(['prompt' => 'rated up', 'comment' => 'Great']);
        QueryRun::factory()->createOne(['prompt' => 'unrated']);

        $this->get(route('admin.feedback.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/feedback/index')
                ->where('runs.total', 1)
                ->where('runs.data.0.prompt', 'rated up')
                ->where('runs.data.0.rating', 'up')
                ->where('runs.data.0.comment', 'Great'));
    }

    public function test_filters_by_rating(): void
    {
        QueryRun::factory()->ratedUp()->createOne(['prompt' => 'liked']);
        QueryRun::factory()->ratedDown()->createOne(['prompt' => 'disliked']);

        $this->get(route('admin.feedback.index', ['rating' => 'down']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('runs.total', 1)
                ->where('runs.data.0.prompt', 'disliked'));
    }

    public function test_rejects_invalid_rating_filter(): void
    {
        $this->get(route('admin.feedback.index', ['rating' => 'meh']))
            ->assertSessionHasErrors('rating');
    }
}
