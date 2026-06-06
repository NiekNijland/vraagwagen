<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Rating;
use App\Http\Controllers\Controller;
use App\Models\QueryRun;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use MongoDB\Laravel\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FeedbackController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(Request $request): InertiaResponse
    {
        $rating = $this->validatedRating($request);

        $runs = $this->orderedRatedQuery($rating)
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(static function ($run): array {
                assert($run instanceof QueryRun);

                return [
                    'id' => $run->id,
                    'slug' => $run->slug,
                    'prompt' => $run->prompt,
                    'locale' => $run->locale,
                    'rating' => $run->rating?->value,
                    'comment' => $run->comment,
                    'ratedAt' => $run->rated_at?->toIso8601String(),
                    'createdAt' => $run->created_at->toIso8601String(),
                ];
            });

        return Inertia::render('admin/feedback/index', [
            'runs' => $runs,
            'filters' => ['rating' => $rating],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rating = $this->validatedRating($request);

        return response()->streamDownload(function () use ($rating): void {
            $out = fopen('php://output', 'w');
            assert($out !== false);

            fputcsv($out, ['rated_at', 'rating', 'slug', 'locale', 'prompt', 'comment']);

            /** @var Collection<int, QueryRun> $runs */
            $runs = $this->orderedRatedQuery($rating)->get();

            foreach ($runs as $run) {
                fputcsv($out, [
                    $run->rated_at?->toIso8601String(),
                    $run->rating?->value,
                    $run->slug,
                    $run->locale,
                    $run->prompt,
                    $run->comment,
                ]);
            }

            fclose($out);
        }, 'feedback-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return Builder<QueryRun>
     */
    private function ratedQuery(?string $rating): Builder
    {
        /** @var Builder<QueryRun> $query */
        $query = QueryRun::query()->where('rating', '!=', null);

        if (is_string($rating)) {
            $query->where('rating', $rating);
        }

        return $query;
    }

    /**
     * @return Builder<QueryRun>
     */
    private function orderedRatedQuery(?string $rating): Builder
    {
        $query = $this->ratedQuery($rating);

        // @phpstan-ignore-next-line laravel-mongodb exposes orderBy at runtime but PHPStan narrows to the base builder.
        $query->orderBy('rated_at', 'desc');

        return $query;
    }

    private function validatedRating(Request $request): ?string
    {
        /** @var array{rating: ?string} $validated */
        $validated = $request->validate([
            'rating' => ['nullable', Rule::enum(Rating::class)],
        ]);

        return $validated['rating'] ?? null;
    }
}
