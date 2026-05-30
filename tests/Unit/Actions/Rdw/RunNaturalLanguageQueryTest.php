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
use App\Services\QueryPlan\ResultNormalizer;
use App\Services\QueryPlan\SocrataStorageTypes;
use App\Services\QueryPlan\StepReferenceResolver;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Psr7Response;
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

    public function test_falls_back_to_unsupported_when_a_step_reference_cannot_resolve(): void
    {
        // q1 returns no rows, so {{q1.Brand}} has no value to substitute → StepReferenceException.
        $this->fakeProgram([
            'queries' => [
                [
                    'id' => 'q1',
                    'dataset' => 'RegisteredVehicles',
                    'where' => [['field' => 'LicensePlate', 'op' => 'eq', 'value' => 'NOPLATE']],
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

        $action = $this->actionFor([[]]);

        $result = $action->execute('Same brand as NOPLATE?', Locale::English);

        self::assertSame(DisplayHint::Unsupported, $result->plan->display);
        self::assertSame([], $result->rows);
        // Steps before the failure still appear in the ledger so the debug panel can show progress.
        self::assertCount(1, $result->steps);
    }

    public function test_estimates_cost_from_response_usage(): void
    {
        config()->set('rdwai.model_prices', [
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

    /**
     * @param  list<list<array<string, mixed>>>  $rdwResponses  one rows-array per executed Plan
     */
    private function actionFor(array $rdwResponses): RunNaturalLanguageQuery
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
        $rdw = new Rdw(http: new SocrataClient(new RdwConfiguration, $guzzle));
        $schemas = new SchemaRegistry;
        $storageTypes = new SocrataStorageTypes($schemas);
        $assembler = new QueryAssembler($rdw, $storageTypes, new FieldCaster($schemas));
        $normalizer = new ResultNormalizer($schemas);

        return new RunNaturalLanguageQuery(
            planRunner: new PlanRunner($rdw, $assembler, $normalizer, retryBackoffMs: 0),
            costEstimator: new CostEstimator((array) config('rdwai.model_prices', [])),
            programFactory: new QueryProgramFactory(
                new PlanFactory($schemas),
                new PresentationFactory,
            ),
            referenceResolver: new StepReferenceResolver,
            derivation: new Derivation,
        );
    }

    /**
     * @param  array<string, mixed>  $program
     */
    private function fakeProgram(array $program, ?Usage $usage = null, string $model = 'fake'): void
    {
        QueryProgramAgent::fake([
            new StructuredTextResponse(
                $program,
                json_encode($program, JSON_THROW_ON_ERROR),
                $usage ?? new Usage,
                new Meta('openai', $model),
            ),
        ]);
    }
}
