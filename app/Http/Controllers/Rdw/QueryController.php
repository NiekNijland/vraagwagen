<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rdw;

use App\Actions\Rdw\QueryExecutionException;
use App\Actions\Rdw\RunNaturalLanguageQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rdw\RunQueryRequest;
use App\Services\QueryPlan\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;
use NiekNijland\RDW\Exceptions\RateLimitException;
use NiekNijland\RDW\Exceptions\RdwException;
use Throwable;

final class QueryController extends Controller
{
    public function index(): InertiaResponse
    {
        /** @var list<string> $examples */
        $examples = (array) config('rdwai.examples', []);

        return Inertia::render('query/index', [
            'examples' => $examples,
        ]);
    }

    public function run(RunQueryRequest $request, RunNaturalLanguageQuery $action): JsonResponse
    {
        try {
            $result = $action->execute($request->string('prompt')->toString());
        } catch (RateLimitException $e) {
            return response()->json([
                'error' => 'RDW rate limit reached. Try again in ' . $e->retryAfterSeconds . 's.',
            ], 429);
        } catch (QueryExecutionException $e) {
            $serialisedPlan = $this->serializePlan($e->plan);
            Log::warning('RDW query failed', [
                'message' => $e->getMessage(),
                'plan' => $serialisedPlan,
            ]);

            return response()->json([
                'error' => 'The generated query was rejected by RDW. Try rephrasing your question.',
                'plan' => $serialisedPlan,
            ], 422);
        } catch (InvalidArgumentException $e) {
            // Field-name / alias / enum validation failures from PlanFactory or
            // PlanRunner. The messages reference internal field names; safe but
            // noisy, so return them under a generic envelope.
            Log::info('RDW plan invalid', ['message' => $e->getMessage()]);

            return response()->json([
                'error' => 'The generated query was malformed. Try rephrasing your question.',
            ], 422);
        } catch (RdwException $e) {
            Log::warning('RDW package error', ['message' => $e->getMessage()]);

            return response()->json([
                'error' => 'The RDW open-data service rejected the query.',
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Something went wrong building or running the query.',
            ], 500);
        }

        return response()->json([
            'plan' => $this->serializePlan($result['plan']),
            'soql' => $result['soql'],
            'rows' => $result['rows'],
            'displayHint' => $result['plan']->display->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlan(Plan $plan): array
    {
        return [
            'where' => array_map(static fn ($c): array => [
                'field' => $c->field,
                'op' => $c->op->value,
                'value' => $c->value,
            ], $plan->where),
            'select' => $plan->select,
            'groupBy' => $plan->groupBy,
            'aggregates' => array_map(static fn ($a): array => [
                'fn' => $a->fn->value,
                'field' => $a->field,
                'alias' => $a->alias,
            ], $plan->aggregates),
            'orderBy' => array_map(static fn ($o): array => [
                'expr' => $o->expr,
                'direction' => $o->direction->value,
            ], $plan->orderBy),
            'limit' => $plan->limit,
            'display' => $plan->display->value,
            'explanation' => $plan->explanation,
        ];
    }
}
