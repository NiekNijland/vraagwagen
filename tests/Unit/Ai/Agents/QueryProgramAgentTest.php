<?php

declare(strict_types=1);

namespace Tests\Unit\Ai\Agents;

use App\Ai\Agents\QueryProgramAgent;
use App\Enums\Locale;
use App\Services\QueryPlan\PromptBuilder;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Attributes\Temperature;
use NiekNijland\RDW\Schema\SchemaRegistry;
use ReflectionAttribute;
use ReflectionClass;
use Tests\TestCase;

final class QueryProgramAgentTest extends TestCase
{
    public function test_agent_uses_strict_low_temperature_structured_output_settings(): void
    {
        $attributes = (new ReflectionClass(QueryProgramAgent::class))->getAttributes();
        $attributeNames = array_map(
            static fn (ReflectionAttribute $attribute): string => $attribute->getName(),
            $attributes,
        );

        self::assertContains(Strict::class, $attributeNames);
        self::assertContains(MaxTokens::class, $attributeNames);
        self::assertContains(Temperature::class, $attributeNames);
    }

    public function test_agent_builds_instructions_for_the_selected_locale(): void
    {
        $agent = new QueryProgramAgent(
            new PromptBuilder($this->app->make(SchemaRegistry::class)),
            Locale::Dutch,
        );

        self::assertStringContainsString('RDW', $agent->instructions());
        self::assertSame('gpt-4.1-mini', $agent->model());
    }
}
