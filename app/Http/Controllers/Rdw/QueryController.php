<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rdw;

use App\Actions\Rdw\GetPlatformStats;
use App\Actions\Rdw\PersistQueryRun;
use App\Actions\Rdw\QueryExecutionException;
use App\Actions\Rdw\RunNaturalLanguageQuery;
use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rdw\RunQueryRequest;
use App\Http\Requests\Rdw\SubmitFeedbackRequest;
use App\Models\QueryRun;
use App\Services\QueryPlan\PlanPresenter;
use App\Services\QueryPlan\TokenUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\InsufficientCreditsException as AiInsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException as AiProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException as AiRateLimitedException;
use NiekNijland\RDW\Exceptions\RateLimitException;
use NiekNijland\RDW\Exceptions\RdwException;
use Throwable;

final class QueryController extends Controller
{
    private const string CLIENT_COOKIE = 'rdw_client';

    private const int CLIENT_COOKIE_MINUTES = 525_600;

    /** Same length as the slug — short enough to quote, long enough to be uniquely searchable. */
    private const int CORRELATION_ID_LENGTH = 10;

    public function index(Request $request, GetPlatformStats $platformStats, string $locale, ?string $slug = null): InertiaResponse
    {
        $sharedRun = null;

        if (is_string($slug) && $slug !== '') {
            $run = QueryRun::query()->where('slug', $slug)->first();

            if (! $run instanceof QueryRun) {
                abort(404);
            }

            $sharedRun = $this->serializeRun($run, $this->currentClientToken($request));
        }

        return Inertia::render('query/index', [
            'sharedRun' => $sharedRun,
            // Authoritative prompt bounds so the composer's client-side limits can't drift from
            // the server-side validation in RunQueryRequest.
            'promptMinLength' => (int) config('vraagwagen.prompt.min_length', 3),
            'promptMaxLength' => (int) config('vraagwagen.prompt.max_length', 2000),
            // Deferred so the first paint never waits on the (cached) RDW vehicle count.
            'platformStats' => Inertia::defer(static fn (): array => $platformStats->execute()),
        ])->withViewData([
            'meta' => $this->queryPageMeta($request, $locale, $sharedRun),
        ]);
    }

    public function run(
        RunQueryRequest $request,
        RunNaturalLanguageQuery $action,
        PersistQueryRun $persist,
    ): JsonResponse {
        $prompt = $request->string('prompt')->toString();
        $locale = Locale::tryFrom(app()->getLocale()) ?? Locale::English;

        // Threads every log entry, persisted QueryRun, and error response from this request so a
        // user-reported failure can be traced back to its logs without their slug (failures never
        // persist a row, so the slug isn't available on the error path).
        $correlationId = Str::random(self::CORRELATION_ID_LENGTH);
        Log::withContext(['correlation_id' => $correlationId]);

        try {
            $result = $action->execute($prompt, $locale);
        } catch (RateLimitException $e) {
            return response()->json([
                'error' => __('query.errors.rate_limited', ['seconds' => $e->retryAfterSeconds]),
                'correlationId' => $correlationId,
            ], 429);
        } catch (QueryExecutionException $e) {
            $serialisedPlan = PlanPresenter::toArray($e->plan);
            Log::warning('RDW query failed', [
                'message' => $e->getMessage(),
                'transient' => $e->isTransient,
                'plan' => $serialisedPlan,
                'soql' => $e->soql,
                'url' => $e->url,
                'responseBody' => $e->responseBody,
            ]);

            // Transient upstream failures get a retryable 504, not a rephrase-it 422.
            return response()->json([
                'error' => __($e->isTransient ? 'query.errors.timeout' : 'query.errors.rejected'),
                'plan' => $serialisedPlan,
                'soql' => $e->soql,
                'url' => $e->url,
                // Raw upstream body may carry internal detail; only expose in debug.
                'responseBody' => (bool) config('app.debug') ? $e->responseBody : null,
                'correlationId' => $correlationId,
            ], $e->isTransient ? 504 : 422);
        } catch (AiException $e) {
            $status = $this->aiFailureStatus($e);
            $errorKey = $status === 504
                ? 'query.errors.timeout'
                : 'query.errors.service_unavailable';

            Log::warning('AI query planner failed', [
                'message' => $e->getMessage(),
                'status' => $status,
            ]);

            return response()->json([
                'error' => __($errorKey),
                'correlationId' => $correlationId,
            ], $status);
        } catch (InvalidArgumentException $e) {
            // Plan validation errors reference internal field names; return the localized fallback.
            Log::info('RDW plan invalid', ['message' => $e->getMessage()]);

            return response()->json([
                'error' => __('query.errors.malformed'),
                'correlationId' => $correlationId,
            ], 422);
        } catch (RdwException $e) {
            Log::warning('RDW package error', ['message' => $e->getMessage()]);

            return response()->json([
                'error' => __('query.errors.rejected'),
                'correlationId' => $correlationId,
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => __('query.errors.unexpected'),
                'correlationId' => $correlationId,
            ], 500);
        }

        $steps = PlanPresenter::stepsToArray($result->steps);
        $presentation = PlanPresenter::presentationToArray($result->presentation, $result->derived);

        $user = $request->user();
        $slug = null;

        try {
            $run = $persist->execute(
                prompt: $prompt,
                locale: app()->getLocale(),
                plan: $result->plan,
                rows: $result->rows,
                soql: $result->soql,
                url: $result->url,
                userId: $user !== null ? (string) $user->getAuthIdentifier() : null,
                model: $result->model,
                tokens: $result->tokens,
                estimatedCost: $result->estimatedCost,
                steps: $steps,
                presentation: $presentation,
                correlationId: $correlationId,
            );

            $slug = $run->slug;
        } catch (Throwable $e) {
            report($e);

            Log::warning('Query result could not be persisted', [
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json(array_filter([
            'slug' => $slug,
            'correlationId' => $correlationId,
            'plan' => PlanPresenter::toArray($result->plan),
            'soql' => $result->soql,
            'url' => $result->url,
            'rows' => $result->rows,
            'displayHint' => $result->plan->display->value,
            'steps' => $steps,
            'presentation' => $presentation,
            'model' => $result->model,
            'tokens' => $result->tokens->toArray(),
            'estimatedCost' => $result->estimatedCost,
        ], static fn (mixed $value): bool => $value !== null));
    }

    public function feedback(SubmitFeedbackRequest $request, string $slug): JsonResponse
    {
        $run = QueryRun::query()->where('slug', $slug)->first();

        if (! $run instanceof QueryRun) {
            return response()->json(['error' => __('query.errors.not_found')], 404);
        }

        // Feedback belongs to the client that first left it; an unrated run is claimable by anyone.
        $client = $this->issueClientToken($request);
        if (is_string($run->rated_by) && $run->rated_by !== '' && $run->rated_by !== $client) {
            return response()->json(['error' => __('query.errors.feedback_forbidden')], 403);
        }

        $run->fill([
            'rating' => $request->validated('rating'),
            'comment' => $request->validated('comment'),
            'rated_at' => now(),
            'rated_by' => $client,
        ])->save();

        return response()->json([
            'rating' => $run->rating,
            'comment' => $run->comment,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRun(QueryRun $run, ?string $clientToken): array
    {
        $isAuthor = $clientToken !== null && $run->rated_by === $clientToken;

        return [
            'slug' => $run->slug,
            'correlationId' => $run->correlation_id,
            'prompt' => $run->prompt,
            'locale' => $run->locale,
            'plan' => PlanPresenter::normalisePersisted($run->plan),
            'soql' => $run->soql,
            'url' => $run->url,
            'rows' => $run->rows,
            'displayHint' => $run->display_hint,
            'steps' => PlanPresenter::normalisePersistedSteps($run->steps),
            'presentation' => $run->presentation,
            'rating' => $run->rating,
            // Only the author sees their own free-text comment.
            'comment' => $isAuthor ? $run->comment : null,
            // Coalesce to '' so the TS shape stays non-nullable.
            'model' => $run->model ?? '',
            'tokens' => TokenUsage::fromQueryRun($run)->toArray(),
            'estimatedCost' => $run->estimated_cost,
        ];
    }

    private function currentClientToken(Request $request): ?string
    {
        $token = $request->cookie(self::CLIENT_COOKIE);

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function issueClientToken(Request $request): string
    {
        $existing = $this->currentClientToken($request);
        if ($existing !== null) {
            return $existing;
        }

        $token = Str::random(40);
        Cookie::queue(Cookie::make(
            name: self::CLIENT_COOKIE,
            value: $token,
            minutes: self::CLIENT_COOKIE_MINUTES,
            httpOnly: true,
        ));

        return $token;
    }

    /**
     * @param array<string, mixed>|null $sharedRun
     * @return array<string, string>
     */
    private function queryPageMeta(Request $request, string $locale, ?array $sharedRun): array
    {
        $isSharedRun = $sharedRun !== null;
        $defaultTitle = __('pages.query.title', locale: $locale);
        $defaultDescription = __('pages.query.metaDescription', locale: $locale);
        $title = $isSharedRun
            ? sprintf('%s | vraagwagen.nl', $sharedRun['prompt'])
            : (is_string($defaultTitle) ? $defaultTitle : 'vraagwagen.nl');
        $description = $isSharedRun
            ? (string) ($sharedRun['presentation']['explanation']
                ?? $sharedRun['plan']['explanation']
                ?? $sharedRun['prompt'])
            : (is_string($defaultDescription) ? $defaultDescription : 'vraagwagen.nl');
        $url = $request->fullUrl();
        $image = $request->root() . '/apple-touch-icon.png';

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $url,
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogType' => $isSharedRun ? 'article' : 'website',
            'ogUrl' => $url,
            'ogImage' => $image,
            'twitterCard' => 'summary_large_image',
            'twitterTitle' => $title,
            'twitterDescription' => $description,
            'twitterImage' => $image,
        ];
    }

    private function aiFailureStatus(AiException $e): int
    {
        if (
            $e instanceof AiRateLimitedException
            || $e instanceof AiProviderOverloadedException
            || $e instanceof AiInsufficientCreditsException
        ) {
            return Response::HTTP_SERVICE_UNAVAILABLE;
        }

        $message = strtolower($e->getMessage());
        $previousMessage = strtolower((string) $e->getPrevious()?->getMessage());

        if (str_contains($message, 'timed out') || str_contains($previousMessage, 'timed out')) {
            return Response::HTTP_GATEWAY_TIMEOUT;
        }

        return Response::HTTP_SERVICE_UNAVAILABLE;
    }
}
