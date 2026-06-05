<?php

declare(strict_types=1);

namespace Tests\Feature\Rdw;

use App\Enums\Rating;
use App\Models\QueryRun;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class QueryShareTest extends TestCase
{
    public function test_home_passes_shared_run_when_slug_in_path_matches_a_persisted_run(): void
    {
        QueryRun::factory()->createOne([
            'slug' => 'sharedabc1',
            'prompt' => 'shared prompt',
        ]);

        $this->get(route('rdw.query.shared', 'sharedabc1'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('query/index')
                    ->where('sharedRun.slug', 'sharedabc1')
                    ->where('sharedRun.prompt', 'shared prompt'),
            );
    }

    public function test_unknown_share_slug_returns_a_404(): void
    {
        $this->get(route('rdw.query.shared', 'nope1234'))
            ->assertNotFound();
    }

    public function test_home_passes_null_shared_run_when_slug_is_absent(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('query/index')
                    ->where('sharedRun', null),
            );
    }

    public function test_shared_run_hides_the_feedback_comment_from_other_visitors(): void
    {
        QueryRun::factory()->ratedUp()->createOne([
            'slug' => 'commentab12',
            'comment' => 'A private note from the author.',
            'rated_by' => 'client-author',
        ]);

        // A stranger sees the rating but never the author's free-text comment.
        $this->withCookie('rdw_client', 'client-stranger')
            ->get(route('rdw.query.shared', 'commentab12'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('query/index')
                    ->where('sharedRun.rating', Rating::Up->value)
                    ->where('sharedRun.comment', null),
            );
    }

    public function test_shared_run_shows_the_comment_back_to_its_author(): void
    {
        QueryRun::factory()->ratedUp()->createOne([
            'slug' => 'commentcd34',
            'comment' => 'A private note from the author.',
            'rated_by' => 'client-author',
        ]);

        $this->withCookie('rdw_client', 'client-author')
            ->get(route('rdw.query.shared', 'commentcd34'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('query/index')
                    ->where('sharedRun.comment', 'A private note from the author.'),
            );
    }

    public function test_shared_run_predating_token_columns_defaults_to_empty_model_and_zero_tokens(): void
    {
        // Legacy rows lack model/token/cost columns; the controller must coalesce.
        QueryRun::factory()->createOne([
            'slug' => 'legacyabc12',
            'prompt' => 'legacy prompt',
        ]);

        $this->get(route('rdw.query.shared', 'legacyabc12'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('query/index')
                    ->where('sharedRun.model', '')
                    ->where('sharedRun.tokens.prompt', 0)
                    ->where('sharedRun.tokens.completion', 0)
                    ->where('sharedRun.tokens.cacheRead', 0)
                    ->where('sharedRun.tokens.thought', 0)
                    ->where('sharedRun.estimatedCost', null),
            );
    }
}
