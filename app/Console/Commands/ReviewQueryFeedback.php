<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Rating;
use App\Models\QueryRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('app:review-query-feedback {--limit=50 : Maximum feedback rows to show} {--rating= : Filter by up or down}')]
#[Description('Review stored query feedback from shared runs')]
final class ReviewQueryFeedback extends Command
{
    public function handle(): int
    {
        $requestedRating = $this->option('rating');

        /** @var Collection<int, QueryRun> $runs */
        $runs = QueryRun::all()
            ->filter(static fn (QueryRun $run): bool => $run->rating !== null);

        if (is_string($requestedRating) && $requestedRating !== '') {
            $rating = Rating::tryFrom($requestedRating);

            if ($rating === null) {
                $this->error('The --rating option must be "up" or "down".');

                return self::FAILURE;
            }

            $runs = $runs->filter(static fn (QueryRun $run): bool => $run->rating === $rating);
        }

        /** @var Collection<int, QueryRun> $runs */
        $runs = $runs
            ->sortByDesc(static fn (QueryRun $run): int => $run->rated_at?->getTimestamp() ?? $run->created_at->getTimestamp())
            ->take((int) $this->option('limit'))
            ->values();

        if ($runs->isEmpty()) {
            $this->info('No query feedback found.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($runs as $run) {
            if ($run->rating === null) {
                continue;
            }

            $rows[] = [
                $run->rated_at?->toDateTimeString() ?? $run->created_at->toDateTimeString(),
                $run->rating->value,
                $run->slug,
                $run->prompt,
                $run->comment ?? '',
            ];
        }

        $this->table(['Rated', 'Rating', 'Slug', 'Prompt', 'Comment'], $rows);

        return self::SUCCESS;
    }
}
