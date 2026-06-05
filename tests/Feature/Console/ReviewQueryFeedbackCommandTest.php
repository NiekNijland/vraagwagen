<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\Rating;
use App\Models\QueryRun;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class ReviewQueryFeedbackCommandTest extends TestCase
{
    public function test_command_lists_saved_feedback(): void
    {
        $ratedAt = now();

        QueryRun::factory()->createOne([
            'slug' => 'feedback123',
            'prompt' => 'How many Teslas?',
            'rating' => Rating::Up,
            'comment' => 'Clear answer.',
            'rated_at' => $ratedAt,
        ]);

        /** @var PendingCommand $command */
        $command = $this->artisan('app:review-query-feedback --limit=5');

        $command
            ->expectsTable(
                ['Rated', 'Rating', 'Slug', 'Prompt', 'Comment'],
                [[
                    $ratedAt->toDateTimeString(),
                    'up',
                    'feedback123',
                    'How many Teslas?',
                    'Clear answer.',
                ]],
            )
            ->assertSuccessful();
    }

    public function test_command_rejects_invalid_rating_filters(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('app:review-query-feedback --rating=maybe');

        $command
            ->expectsOutputToContain('The --rating option must be "up" or "down".')
            ->assertFailed();
    }
}
