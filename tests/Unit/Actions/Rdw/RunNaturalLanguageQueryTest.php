<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Rdw;

use App\Actions\Rdw\RunNaturalLanguageQuery;
use App\Ai\Agents\QueryProgramAgent;
use App\Enums\Locale;
use App\Services\QueryPlan\CostEstimator;
use App\Services\QueryPlan\Derivation;
use App\Services\QueryPlan\DeriveOp;
use App\Services\QueryPlan\DisplayHint;
use App\Services\QueryPlan\FieldCaster;
use App\Services\QueryPlan\PlanFactory;
use App\Services\QueryPlan\PlanRunner;
use App\Services\QueryPlan\PresentationFactory;
use App\Services\QueryPlan\QueryAssembler;
use App\Services\QueryPlan\QueryProgramFactory;
use App\Services\QueryPlan\RefusalReason;
use App\Services\QueryPlan\ResultNormalizer;
use App\Services\QueryPlan\SocrataStorageTypes;
use App\Services\QueryPlan\StepReferenceResolver;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use InvalidArgumentException;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;
use NiekNijland\RDW\Http\Configuration as RdwConfiguration;
use NiekNijland\RDW\Http\SocrataClient;
use NiekNijland\RDW\Rdw;
use NiekNijland\RDW\Schema\SchemaRegistry;
use Tests\TestCase;

final class RunNaturalLanguageQueryTest extends TestCase
{
    public function test_orchestrates_a_single_query_program_end_to_end(): void
    {
        $this->fakeProgram([
            'queries' => [[
                'id' => 'q1',
                'dataset' => 'RegisteredVehicles',
                'where' => [['field' => 'Brand', 'op' => 'eq', 'value' => 'VOLKSWAGEN']],
                'select' => [], 'groupBy' => [],
                'aggregates' => [['fn' => 'count', 'field' => '*', 'alias' => 'n']],
                'orderBy' => [], 'limit' => null, 'display' => 'count',
                'explanation' => 'Counts VWs.',
            ]],
            'presentation' => [
                'resultRef' => 'q1',
                'display' => 'count',
                'derive' => null,
                'explanation' => 'Counts VWs.',
            ],
        ], usage: new Usage(promptTokens: 800, completionTokens: 120), model: 'gpt-4.1-nano');

        $action = $this->actionFor([[['n' => '42']]]);

        $result = $action->execute('How many VWs?', Locale::English);

        self::assertSame(DisplayHint::Count, $result->plan->display);
        self::assertSame('gpt-4.1-nano', $result->model);
        self::assertSame(800, $result->tokens->prompt);
        self::assertSame(120, $result->tokens->completion);
        self::assertNotNull($result->presentation);
        self::assertNull($result->derived);
        self::assertCount(1, $result->steps);
    }

    public function test_resolves_a_step_reference_before_running_the_dependent_query(): void
    {
        $this->fakeProgram([
            'queries' => [
                [
                    'id' => 'q1',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [['field' => 'LicensePlate', 'op' => 'eq', 'value' => '1-ZTZ-08']],
                    'select' => ['Brand', 'CommercialName'],
                    'groupBy' => [], 'aggregates' => [], 'orderBy' => [],
                    'limit' => 1, 'display' => 'record', 'explanation' => '',
                ],
                [
                    'id' => 'q2',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [
                        ['field' => 'Brand', 'op' => 'eq', 'value' => '{{q1.Brand}}'],
                        ['field' => 'CommercialName', 'op' => 'eq', 'value' => '{{q1.CommercialName}}'],
                    ],
                    'select' => [], 'groupBy' => [],
                    'aggregates' => [['fn' => 'count', 'field' => '*', 'alias' => 'n']],
                    'orderBy' => [], 'limit' => null, 'display' => 'count',
                    'explanation' => '',
                ],
            ],
            'presentation' => [
                'resultRef' => 'q2',
                'display' => 'count',
                'derive' => null,
                'explanation' => '',
            ],
        ]);

        $action = $this->actionFor([
            [['merk' => 'VOLKSWAGEN', 'handelsbenaming' => 'GOLF']],
            [['n' => '1234']],
        ]);

        $result = $action->execute('Same model as 1-ZTZ-08?', Locale::English);

        self::assertCount(2, $result->steps);
        // q2's resolved where clause must carry the substituted literal, not the {{q1.Brand}} token.
        self::assertSame('VOLKSWAGEN', $result->steps[1]->plan->where[0]->value);
        self::assertSame('GOLF', $result->steps[1]->plan->where[1]->value);
    }

    public function test_computes_a_percentage_derive_across_two_scalar_queries(): void
    {
        $this->fakeProgram([
            'queries' => [
                [
                    'id' => 'q1',
                    'dataset' => 'RegisteredVehicleFuels',
                    'where' => [['field' => 'NetMaximumPower', 'op' => 'gt', 'value' => '150']],
                    'select' => [], 'groupBy' => [],
                    'aggregates' => [['fn' => 'count_distinct', 'field' => 'LicensePlate', 'alias' => 'n']],
                    'orderBy' => [], 'limit' => null, 'display' => 'count', 'explanation' => '',
                ],
                [
                    'id' => 'q2',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [], 'select' => [], 'groupBy' => [],
                    'aggregates' => [['fn' => 'count', 'field' => '*', 'alias' => 'n']],
                    'orderBy' => [], 'limit' => null, 'display' => 'count', 'explanation' => '',
                ],
            ],
            'presentation' => [
                'resultRef' => 'derived',
                'display' => 'count',
                'derive' => [
                    'op' => 'percentage',
                    'numerator' => 'q1',
                    'denominator' => 'q2',
                ],
                'explanation' => '',
            ],
        ]);

        $action = $this->actionFor([
            [['n' => '250000']],
            [['n' => '10000000']],
        ]);

        $result = $action->execute('Share over 150 kW?', Locale::English);

        self::assertNotNull($result->derived);
        self::assertSame(DeriveOp::Percentage, $result->derived->op);
        // Derivation returns the raw quotient (0–1); the view multiplies by 100 for display.
        self::assertEqualsWithDelta(0.025, $result->derived->value, 1e-6);
        self::assertEqualsWithDelta(250000.0, $result->derived->numerator, 1e-3);
        self::assertEqualsWithDelta(10000000.0, $result->derived->denominator, 1e-3);
    }

    public function test_treats_an_unresolvable_step_reference_as_a_malformed_program(): void
    {
        // q1 returns several rows, so {{q1.Brand}} has no single value to substitute →
        // StepReferenceException. The question is usually answerable and the model just botched the
        // program (a lookup must be single-row), so this surfaces as a malformed-program error (the
        // controller's "try rephrasing" 422), not a fake refusal. An *empty* lookup is different —
        // that degrades to a no-matches refusal, covered below.
        $this->fakeProgram([
            'queries' => [
                [
                    'id' => 'q1',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [['field' => 'Brand', 'op' => 'eq', 'value' => 'KAWASAKI']],
                    'select' => ['Brand'],
                    'groupBy' => [], 'aggregates' => [], 'orderBy' => [],
                    'limit' => 2, 'display' => 'table', 'explanation' => '',
                ],
                [
                    'id' => 'q2',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [['field' => 'Brand', 'op' => 'eq', 'value' => '{{q1.Brand}}']],
                    'select' => [], 'groupBy' => [],
                    'aggregates' => [['fn' => 'count', 'field' => '*', 'alias' => 'n']],
                    'orderBy' => [], 'limit' => null, 'display' => 'count', 'explanation' => '',
                ],
            ],
            'presentation' => [
                'resultRef' => 'q2',
                'display' => 'count',
                'derive' => null,
                'explanation' => '',
            ],
        ]);

        $action = $this->actionFor([[['merk' => 'KAWASAKI'], ['merk' => 'KAWASAKI']]]);

        // A genuine refusal carries a reason + suggestions; a botched derive/reference is a malformed
        // program, so it raises rather than masquerading as a confident "out of scope" answer.
        $this->expectException(InvalidArgumentException::class);

        $action->execute('Same brand as NOPLATE?', Locale::English);
    }

    public function test_maps_a_cross_dataset_overflow_to_a_too_broad_refusal(): void
    {
        // q1 collects plates for a later `in` join; the action forces its limit to LIST_LIMIT + 1
        // so an over-cap match set is detectable. RDW returns that many rows, so resolving q2's
        // `in {{q1.LicensePlate}}` overflows and the whole program degrades to a too_broad refusal.
        $this->fakeProgram([
            'queries' => [
                [
                    'id' => 'q1',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [['field' => 'Brand', 'op' => 'eq', 'value' => 'VOLKSWAGEN']],
                    'select' => ['LicensePlate'],
                    'groupBy' => [], 'aggregates' => [], 'orderBy' => [],
                    'limit' => 1000, 'display' => 'table', 'explanation' => '',
                ],
                [
                    'id' => 'q2',
                    'dataset' => 'RegisteredVehicleFuels',
                    'where' => [['field' => 'LicensePlate', 'op' => 'in', 'value' => '{{q1.LicensePlate}}']],
                    'select' => [], 'groupBy' => [],
                    'aggregates' => [['fn' => 'count', 'field' => '*', 'alias' => 'n']],
                    'orderBy' => [], 'limit' => null, 'display' => 'count', 'explanation' => '',
                ],
            ],
            'presentation' => [
                'resultRef' => 'q2',
                'display' => 'count',
                'derive' => null,
                'explanation' => '',
            ],
        ]);

        // One row past the cap: enough to spill q2's `in` resolution into an overflow.
        $overCap = array_map(
            static fn (int $i): array => ['kenteken' => sprintf('AA-%05d', $i)],
            range(1, StepReferenceResolver::LIST_LIMIT + 1),
        );
        $action = $this->actionFor([$overCap]);

        $result = $action->execute('How many VWs have over 150 kW?', Locale::English);

        self::assertSame(DisplayHint::Unsupported, $result->plan->display);
        self::assertNotNull($result->presentation);
        self::assertNotNull($result->presentation->refusal);
        self::assertSame(RefusalReason::TooBroad, $result->presentation->refusal->reason);

        $expected = __('query.refusal.too_broad', [], Locale::English->value);
        self::assertIsString($expected);
        self::assertSame($expected, $result->presentation->explanation);
        // q2 never ran (overflow aborts before it); q1's recorded plan proves the forced limit.
        self::assertCount(1, $result->steps);
        self::assertSame(StepReferenceResolver::LIST_LIMIT + 1, $result->steps[0]->plan->limit);
    }

    public function test_runs_the_dependent_query_when_a_lookup_sits_exactly_at_the_cap(): void
    {
        // The mirror of the overflow case: a match set *at* the cap is complete, so the forced
        // LIST_LIMIT + 1 fetch returns only LIST_LIMIT rows and the join proceeds normally.
        $this->fakeProgram([
            'queries' => [
                [
                    'id' => 'q1',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [['field' => 'Brand', 'op' => 'eq', 'value' => 'LADA']],
                    'select' => ['LicensePlate'],
                    'groupBy' => [], 'aggregates' => [], 'orderBy' => [],
                    'limit' => 1000, 'display' => 'table', 'explanation' => '',
                ],
                [
                    'id' => 'q2',
                    'dataset' => 'RegisteredVehicleFuels',
                    'where' => [['field' => 'LicensePlate', 'op' => 'in', 'value' => '{{q1.LicensePlate}}']],
                    'select' => [], 'groupBy' => [],
                    'aggregates' => [['fn' => 'count', 'field' => '*', 'alias' => 'n']],
                    'orderBy' => [], 'limit' => null, 'display' => 'count', 'explanation' => 'Counts matches.',
                ],
            ],
            'presentation' => [
                'resultRef' => 'q2',
                'display' => 'count',
                'derive' => null,
                'explanation' => 'Counts matches.',
            ],
        ]);

        $atCap = array_map(
            static fn (int $i): array => ['kenteken' => sprintf('BB-%05d', $i)],
            range(1, StepReferenceResolver::LIST_LIMIT),
        );
        $action = $this->actionFor([$atCap, [['n' => '7']]]);

        $result = $action->execute('How many LADAs have over 150 kW?', Locale::English);

        self::assertSame(DisplayHint::Count, $result->plan->display);
        self::assertCount(2, $result->steps);
        // The lookup is still forced to fetch one past the cap, even though it came back complete.
        self::assertSame(StepReferenceResolver::LIST_LIMIT + 1, $result->steps[0]->plan->limit);
    }

    public function test_short_circuits_a_refusal_presentation_without_running_its_queries(): void
    {
        // The model sometimes attaches a real, runnable query to an unsupported presentation.
        // Presenting its rows next to the refusal explanation would show a confident answer to a
        // question it just declined — the refusal must win and nothing may hit RDW.
        $this->fakeProgram([
            'queries' => [[
                'id' => 'q1',
                'dataset' => 'RegisteredVehicles',
                'where' => [['field' => 'VehicleType', 'op' => 'eq', 'value' => 'Motorfiets']],
                'select' => [], 'groupBy' => [],
                'aggregates' => [['fn' => 'count', 'field' => '*', 'alias' => 'n']],
                'orderBy' => [], 'limit' => null, 'display' => 'count',
                'explanation' => 'Counts motorcycles.',
            ]],
            'presentation' => [
                'resultRef' => 'q1',
                'display' => 'unsupported',
                'derive' => null,
                'explanation' => 'The registry records no theft data.',
                'refusal' => ['reason' => 'no_such_data', 'suggestions' => ['How many motorcycles are registered?']],
            ],
        ]);

        // An empty RDW queue: any executed query would throw from the mock handler.
        $action = $this->actionFor([]);

        $result = $action->execute('How many motorcycles were stolen in 2023?', Locale::English);

        self::assertSame(DisplayHint::Unsupported, $result->plan->display);
        self::assertNotNull($result->presentation);
        self::assertSame(DisplayHint::Unsupported, $result->presentation->display);
        self::assertSame('The registry records no theft data.', $result->presentation->explanation);
        self::assertNotNull($result->presentation->refusal);
        self::assertSame(RefusalReason::NoSuchData, $result->presentation->refusal->reason);
        self::assertSame(['How many motorcycles are registered?'], $result->presentation->refusal->suggestions);
        self::assertSame([], $result->steps);
        self::assertSame([], $result->rows);
    }

    public function test_degrades_an_empty_lookup_to_a_no_matches_refusal(): void
    {
        $this->fakeProgram([
            'queries' => [
                [
                    'id' => 'q1',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [['field' => 'LicensePlate', 'op' => 'eq', 'value' => 'ZZ999Z']],
                    'select' => ['Brand'],
                    'groupBy' => [], 'aggregates' => [], 'orderBy' => [],
                    'limit' => 1, 'display' => 'record', 'explanation' => '',
                ],
                [
                    'id' => 'q2',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [['field' => 'Brand', 'op' => 'eq', 'value' => '{{q1.Brand}}']],
                    'select' => [], 'groupBy' => [],
                    'aggregates' => [['fn' => 'count', 'field' => '*', 'alias' => 'n']],
                    'orderBy' => [], 'limit' => null, 'display' => 'count', 'explanation' => '',
                ],
            ],
            'presentation' => [
                'resultRef' => 'q2',
                'display' => 'count',
                'derive' => null,
                'explanation' => '',
            ],
        ]);

        // The plate lookup matches nothing; resolving q2's reference degrades to a refusal
        // instead of surfacing a malformed-program error for a perfectly fine question.
        $action = $this->actionFor([[]]);

        $result = $action->execute('How many vehicles share a brand with plate ZZ-999-Z?', Locale::English);

        self::assertSame(DisplayHint::Unsupported, $result->plan->display);
        self::assertNotNull($result->presentation);
        self::assertNotNull($result->presentation->refusal);
        self::assertSame(RefusalReason::NoSuchData, $result->presentation->refusal->reason);

        $expected = __('query.refusal.no_matches', [], Locale::English->value);
        self::assertIsString($expected);
        self::assertSame($expected, $result->presentation->explanation);
    }

    public function test_substitutes_step_reference_tokens_in_follow_ups(): void
    {
        $this->fakeProgram([
            'queries' => [
                [
                    'id' => 'q1',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [['field' => 'LicensePlate', 'op' => 'eq', 'value' => '03MBN6']],
                    'select' => ['Brand'],
                    'groupBy' => [], 'aggregates' => [], 'orderBy' => [],
                    'limit' => 1, 'display' => 'record', 'explanation' => '',
                ],
                [
                    'id' => 'q2',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [['field' => 'Brand', 'op' => 'eq', 'value' => '{{q1.Brand}}']],
                    'select' => [], 'groupBy' => [],
                    'aggregates' => [['fn' => 'count', 'field' => '*', 'alias' => 'n']],
                    'orderBy' => [], 'limit' => null, 'display' => 'count', 'explanation' => '',
                ],
            ],
            'presentation' => [
                'resultRef' => 'q2',
                'display' => 'count',
                'derive' => null,
                'explanation' => 'Counts the brand.',
                'followUps' => [
                    'How many {{q1.Brand}} motorcycles are registered per year?',
                    'What about {{q9.Brand}}?',
                ],
            ],
        ]);

        $action = $this->actionFor([
            [['merk' => 'KAWASAKI']],
            [['n' => '87778']],
        ]);

        $result = $action->execute('How many motorcycles share a brand with 03-MBN-6?', Locale::English);

        self::assertNotNull($result->presentation);
        // The resolvable token is substituted with the executed step's value; the follow-up whose
        // token cannot resolve is dropped rather than leaked to the user as a raw template.
        self::assertSame(
            ['How many KAWASAKI motorcycles are registered per year?'],
            $result->presentation->followUps,
        );
    }

    public function test_estimates_cost_from_response_usage(): void
    {
        config()->set('vraagwagen.model_prices', [
            'gpt-4.1-nano' => ['input' => 0.10, 'cached_input' => 0.025, 'output' => 0.40],
        ]);

        $this->fakeProgram([
            'queries' => [[
                'id' => 'q1',
                'dataset' => 'RegisteredVehicles',
                'where' => [], 'select' => [], 'groupBy' => [],
                'aggregates' => [['fn' => 'count', 'field' => '*', 'alias' => 'n']],
                'orderBy' => [], 'limit' => null, 'display' => 'count', 'explanation' => '',
            ]],
            'presentation' => [
                'resultRef' => 'q1',
                'display' => 'count',
                'derive' => null,
                'explanation' => '',
            ],
        ], usage: new Usage(promptTokens: 1_000_000, completionTokens: 500_000), model: 'gpt-4.1-nano');

        $action = $this->actionFor([[['n' => '1']]]);

        $result = $action->execute('count all', Locale::English);

        // 1M input × $0.10 + 500k output × $0.40 = $0.10 + $0.20 = $0.30.
        self::assertEqualsWithDelta(0.30, $result->estimatedCost, 1e-6);
    }

    public function test_reuses_a_cached_query_program_for_identical_locale_and_prompt(): void
    {
        QueryProgramAgent::fake([
            new StructuredTextResponse(
                [
                    'queries' => [[
                        'id' => 'q1',
                        'dataset' => 'RegisteredVehicles',
                        'where' => [], 'select' => [], 'groupBy' => [],
                        'aggregates' => [['fn' => 'count', 'field' => '*', 'alias' => 'n']],
                        'orderBy' => [], 'limit' => null, 'display' => 'count', 'explanation' => '',
                    ]],
                    'presentation' => [
                        'resultRef' => 'q1',
                        'display' => 'count',
                        'derive' => null,
                        'explanation' => '',
                    ],
                ],
                json_encode(['cached' => true], JSON_THROW_ON_ERROR),
                new Usage(promptTokens: 500, completionTokens: 50),
                new Meta('openai', 'gpt-4.1-mini'),
            ),
        ])->preventStrayPrompts();

        $cache = new Repository(new ArrayStore());
        $action = $this->actionFor([[['n' => '1']], [['n' => '1']]], $cache);

        $first = $action->execute('How many vehicles are there?', Locale::English);
        $second = $action->execute('  How many   vehicles are there?  ', Locale::English);

        self::assertSame(500, $first->tokens->prompt);
        self::assertSame(0, $second->tokens->prompt);
        self::assertSame('gpt-4.1-mini', $second->model);
        self::assertNull($second->estimatedCost);
    }

    /**
     * @param list<list<array<string, mixed>>> $rdwResponses one rows-array per executed Plan
     */
    private function actionFor(array $rdwResponses, ?Repository $cache = null): RunNaturalLanguageQuery
    {
        $queue = array_map(
            static fn (array $rows): Psr7Response => new Psr7Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode($rows, JSON_THROW_ON_ERROR),
            ),
            $rdwResponses,
        );

        $stack = HandlerStack::create(new MockHandler($queue));
        $guzzle = new GuzzleClient(['base_uri' => 'https://opendata.rdw.nl/', 'handler' => $stack]);
        $rdw = new Rdw(http: new SocrataClient(new RdwConfiguration(), $guzzle));
        $schemas = new SchemaRegistry();
        $storageTypes = new SocrataStorageTypes($schemas);
        $assembler = new QueryAssembler($rdw, $storageTypes, new FieldCaster($schemas));
        $normalizer = new ResultNormalizer($schemas);

        return new RunNaturalLanguageQuery(
            planRunner: new PlanRunner($rdw, $assembler, $normalizer, retryBackoffMs: 0),
            costEstimator: new CostEstimator((array) config('vraagwagen.model_prices', [])),
            programFactory: new QueryProgramFactory(
                new PlanFactory($schemas),
                new PresentationFactory(),
            ),
            referenceResolver: new StepReferenceResolver(),
            derivation: new Derivation(),
            cache: $cache ?? new Repository(new ArrayStore()),
        );
    }

    /**
     * @param array<string, mixed> $program
     */
    private function fakeProgram(array $program, ?Usage $usage = null, string $model = 'fake'): void
    {
        QueryProgramAgent::fake([
            new StructuredTextResponse(
                $program,
                json_encode($program, JSON_THROW_ON_ERROR),
                $usage ?? new Usage(),
                new Meta('openai', $model),
            ),
        ]);
    }
}
