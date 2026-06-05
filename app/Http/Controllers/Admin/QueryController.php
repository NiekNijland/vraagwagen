<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QueryFilterRequest;
use App\Models\QueryRun;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use MongoDB\Laravel\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class QueryController extends Controller
{
    private const int PER_PAGE = 25;

    /** Result rows can be large; the detail page only needs enough to judge the answer. */
    private const int MAX_DETAIL_ROWS = 50;

    public function index(QueryFilterRequest $request): InertiaResponse
    {
        $filters = $request->filters();

        // laravel-mongodb's orderBy() only accepts string directions, so avoid orderByDesc()
        // (it passes the base builder's SortDirection enum through, which the driver rejects).
        $runs = $this->filteredQuery($filters)
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(function ($run): array {
                assert($run instanceof QueryRun);

                return $this->serializeListItem($run);
            });

        return Inertia::render('admin/queries/index', [
            'runs' => $runs,
            'filters' => $filters,
        ]);
    }

    public function show(string $id): InertiaResponse
    {
        $run = QueryRun::query()->find($id);

        if (! $run instanceof QueryRun) {
            abort(404);
        }

        return Inertia::render('admin/queries/show', [
            'run' => $this->serializeDetail($run),
        ]);
    }

    public function export(QueryFilterRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        return response()->streamDownload(function () use ($filters): void {
            $out = fopen('php://output', 'w');
            assert($out !== false);

            fputcsv($out, [
                'created_at', 'slug', 'locale', 'prompt', 'rating', 'comment', 'model',
                'prompt_tokens', 'completion_tokens', 'cache_read_tokens', 'thought_tokens',
                'estimated_cost', 'user_id', 'correlation_id',
            ]);

            foreach ($this->filteredQuery($filters)->orderBy('created_at', 'desc')->cursor() as $run) {
                assert($run instanceof QueryRun);

                fputcsv($out, [
                    $run->created_at->toIso8601String(),
                    $run->slug,
                    $run->locale,
                    $run->prompt,
                    $run->rating?->value,
                    $run->comment,
                    $run->model,
                    $run->prompt_tokens,
                    $run->completion_tokens,
                    $run->cache_read_tokens,
                    $run->thought_tokens,
                    $run->estimated_cost,
                    $run->user_id,
                    $run->correlation_id,
                ]);
            }

            fclose($out);
        }, 'queries-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @param array{search: ?string, rating: ?string, locale: ?string, from: ?string, to: ?string} $filters
     * @return Builder<QueryRun>
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = QueryRun::query();

        if (is_string($filters['search']) && $filters['search'] !== '') {
            $query->where('prompt', 'like', '%' . $filters['search'] . '%');
        }

        if (is_string($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        if (is_string($filters['locale'])) {
            $query->where('locale', $filters['locale']);
        }

        if (is_string($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (is_string($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListItem(QueryRun $run): array
    {
        return [
            'id' => $run->id,
            'slug' => $run->slug,
            'prompt' => $run->prompt,
            'locale' => $run->locale,
            'rating' => $run->rating?->value,
            'hasComment' => is_string($run->comment) && $run->comment !== '',
            'model' => $run->model,
            'totalTokens' => ($run->prompt_tokens ?? 0) + ($run->completion_tokens ?? 0),
            'estimatedCost' => $run->estimated_cost,
            'userId' => $run->user_id,
            'createdAt' => $run->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetail(QueryRun $run): array
    {
        $rows = $run->rows ?? [];

        return [
            'id' => $run->id,
            'slug' => $run->slug,
            'correlationId' => $run->correlation_id,
            'prompt' => $run->prompt,
            'locale' => $run->locale,
            'plan' => $run->plan,
            'soql' => $run->soql,
            'url' => $run->url,
            'rows' => array_slice($rows, 0, self::MAX_DETAIL_ROWS),
            'rowCount' => count($rows),
            'displayHint' => $run->display_hint,
            'steps' => $run->steps,
            'presentation' => $run->presentation,
            'rating' => $run->rating?->value,
            'comment' => $run->comment,
            'ratedAt' => $run->rated_at?->toIso8601String(),
            'model' => $run->model,
            'promptTokens' => $run->prompt_tokens,
            'completionTokens' => $run->completion_tokens,
            'cacheReadTokens' => $run->cache_read_tokens,
            'thoughtTokens' => $run->thought_tokens,
            'estimatedCost' => $run->estimated_cost,
            'userId' => $run->user_id,
            'createdAt' => $run->created_at->toIso8601String(),
        ];
    }
}
