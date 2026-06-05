<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\QueryProgramAgent;
use App\Enums\Locale;
use App\Services\QueryPlan\DeriveOp;
use App\Services\QueryPlan\DisplayHint;
use App\Services\QueryPlan\QueryProgram;
use App\Services\QueryPlan\QueryProgramFactory;
use App\Services\QueryPlan\RefusalReason;
use App\Services\QueryPlan\TargetDataset;
use App\Services\QueryPlan\WhereClause;
use App\Services\QueryPlan\WhereOp;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\Exceptions\RateLimitedException;
use Tests\TestCase;

final class QueryProgramAgentLiveTest extends TestCase
{
    public function test_live_openai_handles_brand_and_model_motorcycle_count_question(): void
    {
        $this->skipUnlessLiveAiIsEnabled();

        $program = $this->askLive('Hoeveel Yamaha MT-07 motorfietsen zijn er geregistreerd?');

        self::assertSame('q1', $program->queries[0]->id);
        self::assertSame(TargetDataset::RegisteredVehicles, $program->queries[0]->plan->dataset);
        self::assertSame(DisplayHint::Count, $program->presentation->display);
        self::assertNull($program->presentation->refusal);
        self::assertTrue($this->hasClause($program->queries[0]->plan->where, 'VehicleType', WhereOp::Equals, 'Motorfiets'));
        self::assertTrue($this->hasClauseWithAllowedOps($program->queries[0]->plan->where, 'Brand', 'YAMAHA', [WhereOp::Equals, WhereOp::Contains]));
        self::assertTrue(
            $this->hasClauseWithAllowedOps($program->queries[0]->plan->where, 'CommercialName', 'MT-07', [WhereOp::Equals, WhereOp::Contains])
            || $this->hasClauseWithAllowedOps($program->queries[0]->plan->where, 'Brand', 'MT-07', [WhereOp::Equals, WhereOp::Contains])
            || count($program->queries[0]->plan->where) >= 2,
        );
    }

    public function test_live_openai_handles_simple_vehicle_type_count_question(): void
    {
        $this->skipUnlessLiveAiIsEnabled();

        $program = $this->askLive('Hoeveel motorfietsen zijn er geregistreerd?');

        self::assertSame(1, count($program->queries));
        self::assertSame(TargetDataset::RegisteredVehicles, $program->queries[0]->plan->dataset);
        self::assertTrue($this->hasClause($program->queries[0]->plan->where, 'VehicleType', WhereOp::Equals, 'Motorfiets'));
        self::assertNull($program->presentation->refusal);
    }

    public function test_live_openai_handles_cross_dataset_power_question(): void
    {
        $this->skipUnlessLiveAiIsEnabled();

        // Bugatti (~115 plates) genuinely fits the 1000-plate lookup cap; Ferrari and Lamborghini
        // exceed it and are now documented refusals.
        $program = $this->askLive('Hoeveel Bugatti personenautos hebben meer dan 150 kW vermogen?');

        self::assertGreaterThanOrEqual(2, count($program->queries));
        self::assertTrue($this->programHasDataset($program, TargetDataset::RegisteredVehicles));
        self::assertTrue($this->programHasDataset($program, TargetDataset::RegisteredVehicleFuels));
        self::assertTrue($this->programHasClauseWithAllowedOps($program, 'Brand', 'BUGATTI', [WhereOp::Equals, WhereOp::Contains]));
        self::assertTrue($this->programHasClause($program, 'VehicleType', WhereOp::Equals, 'Personenauto'));
        self::assertTrue($this->programHasClause($program, 'NetMaximumPower', WhereOp::GreaterThan, '150'));
        self::assertTrue($this->programHasStepReferenceInClause($program, 'LicensePlate'));
    }

    public function test_live_openai_handles_percentage_question_with_derive(): void
    {
        $this->skipUnlessLiveAiIsEnabled();

        $program = $this->askLive('Welk percentage van de personenautos is geel?');

        self::assertNotNull($program->presentation->derive);
        self::assertContains($program->presentation->derive->op, [DeriveOp::Percentage, DeriveOp::GroupShare]);
        self::assertSame(DisplayHint::Count, $program->presentation->display);
        self::assertNull($program->presentation->refusal);
        self::assertSame('GEEL', $program->presentation->derive->selectorValue);
    }

    public function test_live_openai_handles_timeseries_question(): void
    {
        $this->skipUnlessLiveAiIsEnabled();

        $program = $this->askLive('Hoe ontwikkelde het aantal nieuw toegelaten motorfietsen zich per jaar?');

        self::assertSame(DisplayHint::Timeseries, $program->presentation->display);
        self::assertSame(TargetDataset::RegisteredVehicles, $program->queries[0]->plan->dataset);
        self::assertTrue($this->hasClause($program->queries[0]->plan->where, 'VehicleType', WhereOp::Equals, 'Motorfiets'));
    }

    public function test_live_openai_refuses_driver_gender_question(): void
    {
        $this->skipUnlessLiveAiIsEnabled();

        $program = $this->askLive('Welke auto is het populairst onder vrouwen?');

        self::assertNotNull($program->presentation->refusal);
        self::assertSame(RefusalReason::NoSuchData, $program->presentation->refusal->reason);
        self::assertNotSame([], $program->presentation->refusal->suggestions);
        self::assertSame(DisplayHint::Unsupported, $program->presentation->display);
    }

    private function askLive(string $question): QueryProgram
    {
        Config::set('vraagwagen.llm_model', env('VRAAGWAGEN_LIVE_AI_MODEL', 'gpt-4.1-mini'));

        try {
            $response = QueryProgramAgent::make(locale: Locale::Dutch)->ask($question);
        } catch (RateLimitedException $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        self::assertNotSame([], $response->structured);

        return $this->app->make(QueryProgramFactory::class)->fromArray($response->structured);
    }

    /**
     * @param list<WhereClause> $clauses
     */
    private function hasClause(array $clauses, string $field, WhereOp $op, string $value): bool
    {
        foreach ($clauses as $clause) {
            if ($clause->field !== $field || $clause->op !== $op) {
                continue;
            }

            if (str_contains(mb_strtoupper($clause->value), mb_strtoupper($value))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<WhereClause> $clauses
     * @param list<WhereOp> $allowedOps
     */
    private function hasClauseWithAllowedOps(array $clauses, string $field, string $value, array $allowedOps): bool
    {
        foreach ($allowedOps as $op) {
            if ($this->hasClause($clauses, $field, $op, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<WhereClause> $clauses
     */
    private function hasStepReferenceInClause(array $clauses, string $field): bool
    {
        foreach ($clauses as $clause) {
            if ($clause->field !== $field || $clause->op !== WhereOp::In) {
                continue;
            }

            if (preg_match('/^\{\{q\d+\.[A-Za-z][A-Za-z0-9]*\}\}$/', $clause->value) === 1) {
                return true;
            }
        }

        return false;
    }

    private function programHasDataset(QueryProgram $program, TargetDataset $dataset): bool
    {
        foreach ($program->queries as $query) {
            if ($query->plan->dataset === $dataset) {
                return true;
            }
        }

        return false;
    }

    private function programHasClause(QueryProgram $program, string $field, WhereOp $op, string $value): bool
    {
        foreach ($program->queries as $query) {
            if ($this->hasClause($query->plan->where, $field, $op, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<WhereOp> $allowedOps
     */
    private function programHasClauseWithAllowedOps(QueryProgram $program, string $field, string $value, array $allowedOps): bool
    {
        foreach ($program->queries as $query) {
            if ($this->hasClauseWithAllowedOps($query->plan->where, $field, $value, $allowedOps)) {
                return true;
            }
        }

        return false;
    }

    private function programHasStepReferenceInClause(QueryProgram $program, string $field): bool
    {
        foreach ($program->queries as $query) {
            if ($this->hasStepReferenceInClause($query->plan->where, $field)) {
                return true;
            }
        }

        return false;
    }

    private function skipUnlessLiveAiIsEnabled(): void
    {
        if (! filter_var(env('RUN_LIVE_AI_TESTS', false), FILTER_VALIDATE_BOOL)) {
            self::markTestSkipped('Set RUN_LIVE_AI_TESTS=true to run live AI smoke tests.');
        }

        if (! is_string(env('OPENAI_API_KEY')) || env('OPENAI_API_KEY') === '') {
            self::markTestSkipped('OPENAI_API_KEY is required for live AI smoke tests.');
        }
    }
}
