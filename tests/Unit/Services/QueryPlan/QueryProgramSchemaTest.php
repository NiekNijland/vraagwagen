<?php

declare(strict_types=1);

namespace Tests\Unit\Services\QueryPlan;

use App\Services\QueryPlan\QueryProgramSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\ObjectSchema;
use PHPUnit\Framework\TestCase;

final class QueryProgramSchemaTest extends TestCase
{
    public function test_builds_a_program_schema_with_queries_and_presentation(): void
    {
        $built = QueryProgramSchema::build(new JsonSchemaTypeFactory);

        self::assertSame(['queries', 'presentation'], array_keys($built));
    }

    public function test_serialises_to_a_json_schema_without_error(): void
    {
        $built = QueryProgramSchema::build(new JsonSchemaTypeFactory);

        $schema = (new ObjectSchema($built))->toSchema();

        // The query item nests the full plan plus an id; the presentation nests
        // a nullable derive. Assert the shape survives serialisation.
        self::assertSame('object', $schema['type']);
        self::assertArrayHasKey('queries', $schema['properties']);
        self::assertArrayHasKey('presentation', $schema['properties']);
        self::assertSame('array', $schema['properties']['queries']['type']);
        self::assertArrayHasKey('id', $schema['properties']['queries']['items']['properties']);
        self::assertArrayHasKey('derive', $schema['properties']['presentation']['properties']);
    }
}
