<?php

declare(strict_types=1);

namespace Tests\Unit\Ai\Agents;

use App\Services\QueryPlan\QueryProgramSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\ObjectSchema;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\AssertsStrictJsonSchemaObjects;

final class QueryProgramAgentSchemaContractTest extends TestCase
{
    use AssertsStrictJsonSchemaObjects;

    public function test_strict_schema_requires_every_nested_where_property(): void
    {
        $schema = (new ObjectSchema(
            QueryProgramSchema::build(new JsonSchemaTypeFactory()),
            strict: true,
        ))->toSchema();

        $whereItem = $schema['properties']['queries']['items']['properties']['where']['items'];

        self::assertStrictObjectNode($whereItem, 'queries[].where[]');
        self::assertSame(['string', 'null'], $whereItem['properties']['value']['type']);
        self::assertSame(['array', 'null'], $whereItem['properties']['values']['type']);
    }

    public function test_strict_schema_requires_every_nested_presentation_property(): void
    {
        $schema = (new ObjectSchema(
            QueryProgramSchema::build(new JsonSchemaTypeFactory()),
            strict: true,
        ))->toSchema();

        $presentation = $schema['properties']['presentation'];

        self::assertStrictObjectNode($presentation, 'presentation');
    }
}
