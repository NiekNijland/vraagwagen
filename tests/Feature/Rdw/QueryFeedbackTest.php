<?php

declare(strict_types=1);

namespace Tests\Feature\Rdw;

use App\Enums\Rating;
use App\Models\QueryRun;
use Tests\TestCase;

final class QueryFeedbackTest extends TestCase
{
    public function test_feedback_persists_rating_and_comment_on_the_query_run(): void
    {
        QueryRun::factory()->createOne(['slug' => 'fbslug1234']);

        $this->postJson(route('rdw.query.feedback', ['slug' => 'fbslug1234']), [
            'rating' => 'up',
            'comment' => 'Spot on, exactly what I asked for.',
        ])
            ->assertOk()
            ->assertJsonPath('rating', 'up')
            ->assertJsonPath('comment', 'Spot on, exactly what I asked for.');

        $fresh = QueryRun::query()->where('slug', 'fbslug1234')->first();
        self::assertInstanceOf(QueryRun::class, $fresh);
        self::assertSame(Rating::Up, $fresh->rating);
        self::assertSame('Spot on, exactly what I asked for.', $fresh->comment);
        self::assertNotNull($fresh->rated_at);
    }

    public function test_feedback_accepts_a_thumbs_down_without_a_comment(): void
    {
        QueryRun::factory()->createOne(['slug' => 'fbslug2222']);

        $this->postJson(route('rdw.query.feedback', ['slug' => 'fbslug2222']), [
            'rating' => 'down',
        ])
            ->assertOk()
            ->assertJsonPath('rating', 'down')
            ->assertJsonPath('comment', null);
    }

    public function test_feedback_validates_rating(): void
    {
        QueryRun::factory()->createOne(['slug' => 'fbslug3333']);

        $this->postJson(route('rdw.query.feedback', ['slug' => 'fbslug3333']), [
            'rating' => 'sideways',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');

        $this->postJson(route('rdw.query.feedback', ['slug' => 'fbslug3333']), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }

    public function test_feedback_returns_404_for_unknown_slug(): void
    {
        $this->postJson(route('rdw.query.feedback', ['slug' => 'missing123']), [
            'rating' => 'up',
        ])
            ->assertStatus(404)
            ->assertJsonPath('error', 'That query result was not found.');
    }

    public function test_feedback_overwrites_a_previous_rating(): void
    {
        $run = QueryRun::factory()->ratedUp()->createOne(['slug' => 'fbslug4444']);
        self::assertSame(Rating::Up, $run->rating);

        $this->postJson(route('rdw.query.feedback', ['slug' => 'fbslug4444']), [
            'rating' => 'down',
            'comment' => 'Changed my mind',
        ])->assertOk();

        $fresh = QueryRun::query()->where('slug', 'fbslug4444')->first();
        self::assertInstanceOf(QueryRun::class, $fresh);
        self::assertSame(Rating::Down, $fresh->rating);
        self::assertSame('Changed my mind', $fresh->comment);
    }

    public function test_the_same_client_can_update_its_own_rating(): void
    {
        QueryRun::factory()->createOne(['slug' => 'fbslug7777']);

        // Same client token across both requests so this exercises the owner path.
        $this->withCredentials()->withCookie('rdw_client', 'client-alpha');

        $this->postJson(route('rdw.query.feedback', ['slug' => 'fbslug7777']), ['rating' => 'up'])
            ->assertOk();
        $this->postJson(route('rdw.query.feedback', ['slug' => 'fbslug7777']), ['rating' => 'down'])
            ->assertOk();

        $fresh = QueryRun::query()->where('slug', 'fbslug7777')->first();
        self::assertInstanceOf(QueryRun::class, $fresh);
        self::assertSame(Rating::Down, $fresh->rating);
    }

    public function test_a_different_client_cannot_overwrite_someone_elses_rating(): void
    {
        QueryRun::factory()->createOne(['slug' => 'fbslug8888']);

        $this->withCredentials();

        $this->withCookie('rdw_client', 'client-author')
            ->postJson(route('rdw.query.feedback', ['slug' => 'fbslug8888']), [
                'rating' => 'up',
                'comment' => 'Author note',
            ])
            ->assertOk();

        $this->withCookie('rdw_client', 'client-stranger')
            ->postJson(route('rdw.query.feedback', ['slug' => 'fbslug8888']), ['rating' => 'down'])
            ->assertStatus(403);

        $fresh = QueryRun::query()->where('slug', 'fbslug8888')->first();
        self::assertInstanceOf(QueryRun::class, $fresh);
        self::assertSame(Rating::Up, $fresh->rating);
        self::assertSame('Author note', $fresh->comment);
    }

    public function test_feedback_rejects_comments_longer_than_1000_characters(): void
    {
        QueryRun::factory()->createOne(['slug' => 'fbslug5555']);

        $this->postJson(route('rdw.query.feedback', ['slug' => 'fbslug5555']), [
            'rating' => 'up',
            'comment' => str_repeat('x', 1001),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('comment');
    }

    public function test_feedback_is_rate_limited(): void
    {
        QueryRun::factory()->createOne(['slug' => 'fbslug6666']);
        config()->set('vraagwagen.rate_limit.feedback_per_minute', 2);

        // Stable client token so this asserts the rate limiter, not ownership.
        $this->withCredentials()->withCookie('rdw_client', 'client-rate-limit');

        $payload = ['rating' => 'up'];

        $this->postJson(route('rdw.query.feedback', ['slug' => 'fbslug6666']), $payload)->assertOk();
        $this->postJson(route('rdw.query.feedback', ['slug' => 'fbslug6666']), $payload)->assertOk();
        $this->postJson(route('rdw.query.feedback', ['slug' => 'fbslug6666']), $payload)
            ->assertStatus(429);
    }
}
