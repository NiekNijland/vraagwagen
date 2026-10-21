<?php

declare(strict_types=1);

namespace Tests\Unit\Ai\Agents;

use App\Ai\Agents\QueryProgramAgent;
use App\Enums\Locale;
use App\Services\QueryPlan\PromptBuilder;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Enums\Lab;
use NiekNijland\RDW\Schema\SchemaRegistry;
use ReflectionAttribute;
use ReflectionClass;
use Tests\TestCase;

final class QueryProgramAgentTest extends TestCase
{
    public function test_agent_uses_strict_structured_output_settings(): void
    {
        $attributes = (new ReflectionClass(QueryProgramAgent::class))->getAttributes();
        $attributeNames = array_map(
            static fn (ReflectionAttribute $attribute): string => $attribute->getName(),
            $attributes,
        );

        self::assertContains(Strict::class, $attributeNames);
        self::assertContains(MaxTokens::class, $attributeNames);
    }

    public function test_agent_builds_instructions_for_the_selected_locale(): void
    {
        $agent = new QueryProgramAgent(
            new PromptBuilder($this->app->make(SchemaRegistry::class)),
            Locale::Dutch,
        );

        self::assertStringContainsString('RDW', $agent->instructions());
        self::assertSame('gpt-5.4-nano', $agent->model());
    }

    public function test_non_reasoning_model_runs_at_zero_temperature_without_provider_options(): void
    {
        config()->set('vraagwagen.llm_model', 'gpt-4.1-mini');

        $agent = $this->makeAgent();

        self::assertSame(0.0, $agent->temperature());
        self::assertSame([], $agent->providerOptions(Lab::OpenAI));
    }

    public function test_reasoning_model_omits_temperature_and_requests_low_reasoning_effort(): void
    {
        foreach (['gpt-5-nano', 'gpt-5.4-nano'] as $model) {
            config()->set('vraagwagen.llm_model', $model);

            $agent = $this->makeAgent();

            self::assertNull($agent->temperature(), $model);
            self::assertSame(['reasoning' => ['effort' => 'low']], $agent->providerOptions(Lab::OpenAI), $model);
        }
    }

    private function makeAgent(): QueryProgramAgent
    {
        return new QueryProgramAgent(new PromptBuilder($this->app->make(SchemaRegistry::class)));
    }
}
