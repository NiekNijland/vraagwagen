<?php

declare(strict_types=1);

namespace Tests\Unit\Ai\Agents;

use App\Services\QueryPlan\QueryProgramSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\ObjectSchema;
use PHPUnit\Framework\TestCase;

final class QueryProgramAgentSchemaContractTest extends TestCase
{
    public function test_strict_schema_requires_every_nested_where_property(): void
    {
        $schema = (new ObjectSchema(
            QueryProgramSchema::build(new JsonSchemaTypeFactory),
            strict: true,
        ))->toSchema();

        $whereItem = $schema['properties']['queries']['items']['properties']['where']['items'];

        self::assertSame(
            ['field', 'op', 'value', 'values'],
            $whereItem['required'],
            'Strict structured output must require every nested where property or OpenAI rejects the schema.',
        );
        self::assertFalse($whereItem['additionalProperties']);
        self::assertSame(['string', 'null'], $whereItem['properties']['value']['type']);
        self::assertSame(['array', 'null'], $whereItem['properties']['values']['type']);
    }

    public function test_strict_schema_requires_every_nested_presentation_property(): void
    {
        $schema = (new ObjectSchema(
            QueryProgramSchema::build(new JsonSchemaTypeFactory),
            strict: true,
        ))->toSchema();

        $presentation = $schema['properties']['presentation'];

        self::assertSame(
            ['resultRef', 'display', 'derive', 'refusal', 'explanation', 'followUps'],
            $presentation['required'],
        );
        self::assertFalse($presentation['additionalProperties']);
    }
}
