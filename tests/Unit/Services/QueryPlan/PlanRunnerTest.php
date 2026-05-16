<?php

declare(strict_types=1);

namespace Tests\Unit\Services\QueryPlan;

use App\Services\QueryPlan\AggregateClause;
use App\Services\QueryPlan\AggregateFn;
use App\Services\QueryPlan\DisplayHint;
use App\Services\QueryPlan\OrderClause;
use App\Services\QueryPlan\OrderDirection;
use App\Services\QueryPlan\Plan;
use App\Services\QueryPlan\PlanRunner;
use App\Services\QueryPlan\WhereClause;
use App\Services\QueryPlan\WhereOp;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Psr7Response;
use InvalidArgumentException;
use NiekNijland\RDW\Http\Configuration as RdwConfiguration;
use NiekNijland\RDW\Http\SocrataClient;
use NiekNijland\RDW\Rdw;
use PHPUnit\Framework\TestCase;

final class PlanRunnerTest extends TestCase
{
    public function test_row_path_returns_columns_in_select_order_with_pascalcase_keys(): void
    {
        $runner = $this->runnerReturning([
            // The HTTP layer returns rows keyed by Dutch snake_case. The
            // package hydrates them; the runner then renames to PascalCase.
            [
                'kenteken' => '12-AB-345',
                'handelsbenaming' => 'GOLF',
                'merk' => 'VOLKSWAGEN',
                'datum_tenaamstelling_dt' => '2024-01-15',
            ],
        ]);

        $plan = new Plan(
            where: [new WhereClause('Brand', WhereOp::Equals, 'VOLKSWAGEN')],
            // Deliberately reverse the source order to prove select-order wins.
            select: ['CommercialName', 'LicensePlate', 'RegistrationDate'],
            groupBy: [],
            aggregates: [],
            orderBy: [],
            limit: 5,
            display: DisplayHint::Table,
            explanation: '',
        );

        $result = $runner->run($plan);

        self::assertCount(1, $result['rows']);
        self::assertSame(
            ['CommercialName', 'LicensePlate', 'RegistrationDate'],
            array_keys($result['rows'][0]),
        );
        self::assertSame('GOLF', $result['rows'][0]['CommercialName']);
        self::assertSame('12-AB-345', $result['rows'][0]['LicensePlate']);
        // Dates are normalised to YYYY-MM-DD strings.
        self::assertSame('2024-01-15', $result['rows'][0]['RegistrationDate']);
    }

    public function test_aggregate_path_normalises_dutch_keys_to_pascalcase(): void
    {
        $runner = $this->runnerReturning([
            ['eerste_kleur' => 'WIT', 'n' => '42'],
            ['eerste_kleur' => 'ZWART', 'n' => '17'],
        ]);

        $plan = new Plan(
            where: [],
            select: [],
            groupBy: ['PrimaryColor'],
            aggregates: [new AggregateClause(AggregateFn::Count, null, 'n')],
            orderBy: [new OrderClause('n', OrderDirection::Desc)],
            limit: 25,
            display: DisplayHint::Bars,
            explanation: '',
        );

        $result = $runner->run($plan);

        self::assertSame(['PrimaryColor', 'n'], array_keys($result['rows'][0]));
        self::assertSame('WIT', $result['rows'][0]['PrimaryColor']);
        self::assertSame('42', $result['rows'][0]['n']);
    }

    public function test_orderby_accepts_field_names_and_aggregate_aliases_but_rejects_others(): void
    {
        $runner = $this->runnerReturning([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('orderBy expression "totally_random_alias"');

        $runner->run(new Plan(
            where: [],
            select: [],
            groupBy: ['PrimaryColor'],
            aggregates: [new AggregateClause(AggregateFn::Count, null, 'n')],
            orderBy: [new OrderClause('totally_random_alias', OrderDirection::Desc)],
            limit: null,
            display: DisplayHint::Bars,
            explanation: '',
        ));
    }

    public function test_sum_avg_min_max_require_a_field(): void
    {
        $runner = $this->runnerReturning([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Aggregate sum requires a field.');

        $runner->run(new Plan(
            where: [],
            select: [],
            groupBy: [],
            aggregates: [new AggregateClause(AggregateFn::Sum, null, 'total')],
            orderBy: [],
            limit: null,
            display: DisplayHint::Count,
            explanation: '',
        ));
    }

    public function test_soql_params_reflect_where_select_groupby_orderby_and_limit(): void
    {
        $runner = $this->runnerReturning([]);

        $plan = new Plan(
            where: [
                new WhereClause('Brand', WhereOp::Equals, 'VOLKSWAGEN'),
                new WhereClause('CommercialName', WhereOp::Contains, 'GOLF'),
            ],
            select: [],
            groupBy: ['PrimaryColor'],
            aggregates: [new AggregateClause(AggregateFn::Count, null, 'n')],
            orderBy: [new OrderClause('n', OrderDirection::Desc)],
            limit: 25,
            display: DisplayHint::Bars,
            explanation: '',
        );

        $soql = $runner->run($plan)['soql'];

        self::assertArrayHasKey('$where', $soql);
        self::assertStringContainsString("merk = 'VOLKSWAGEN'", $soql['$where']);
        self::assertStringContainsString("contains(handelsbenaming, 'GOLF')", $soql['$where']);
        self::assertArrayHasKey('$group', $soql);
        self::assertStringContainsString('eerste_kleur', $soql['$group']);
        self::assertArrayHasKey('$select', $soql);
        self::assertStringContainsString('count(*) AS n', $soql['$select']);
        self::assertArrayHasKey('$order', $soql);
        self::assertStringContainsString('n DESC', $soql['$order']);
        self::assertSame('25', $soql['$limit']);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function runnerReturning(array $rows): PlanRunner
    {
        $mock = new MockHandler([
            new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode($rows, JSON_THROW_ON_ERROR)),
        ]);

        $stack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient([
            'base_uri' => 'https://opendata.rdw.nl/',
            'handler' => $stack,
        ]);

        $socrata = new SocrataClient(new RdwConfiguration(), $guzzle);
        $rdw = new Rdw(http: $socrata);

        return new PlanRunner($rdw);
    }
}
