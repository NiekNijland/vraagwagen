<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use Carbon\CarbonImmutable;
use NiekNijland\RDW\Schema\DatasetSchema;
use NiekNijland\RDW\Schema\SchemaRegistry;

/**
 * Re-keys RDW result rows from the package's wire shape (Dutch snake_case keys, hydrated record
 * objects) into the public PascalCase enum-case keys the frontend consumes. Aggregate aliases and
 * bucket aliases pass through unchanged — they're already chosen by the plan.
 */
final readonly class ResultNormalizer
{
    public function __construct(private SchemaRegistry $schemas) {}

    /**
     * @param  list<object>  $records
     * @param  list<string>  $select
     * @return list<array<string, mixed>>
     */
    public function fromRecords(array $records, array $select, TargetDataset $dataset): array
    {
        return array_map(fn (object $r): array => $this->recordToArray($r, $select, $dataset), $records);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<AggregateClause>  $aggregates
     * @param  array<string, BucketExpression>  $buckets
     * @return list<array<string, mixed>>
     */
    public function fromProjection(array $rows, array $aggregates, array $buckets, TargetDataset $dataset): array
    {
        $schema = $this->schemas->get($dataset->datasetId());
        $passThrough = [];
        foreach ($aggregates as $agg) {
            $passThrough[$agg->alias] = true;
        }
        foreach ($buckets as $bucket) {
            $passThrough[$bucket->alias] = true;
        }

        $out = [];
        foreach ($rows as $row) {
            $normalised = [];
            foreach ($row as $key => $value) {
                $renamed = isset($passThrough[$key])
                    ? $key
                    : ($schema->byRdwKey[$key]->enumCase ?? $key);
                $normalised[$renamed] = $this->normaliseValue($value);
            }
            $out[] = $normalised;
        }

        return $out;
    }

    /**
     * @param  list<string>  $select
     * @return array<string, mixed>
     */
    private function recordToArray(object $record, array $select, TargetDataset $dataset): array
    {
        $vars = get_object_vars($record);
        $schema = $this->schemas->get($dataset->datasetId());

        if ($select === []) {
            $out = [];
            foreach ($vars as $key => $value) {
                $enumCase = $this->propertyToEnumCase($schema, $key) ?? $key;
                $out[$enumCase] = $this->normaliseValue($value);
            }

            return $out;
        }

        $out = [];
        foreach ($select as $enumCase) {
            $descriptor = $schema->byEnumCase[$enumCase] ?? null;
            if ($descriptor === null) {
                continue;
            }
            if (! array_key_exists($descriptor->propertyName, $vars)) {
                continue;
            }
            $out[$enumCase] = $this->normaliseValue($vars[$descriptor->propertyName]);
        }

        return $out;
    }

    private function propertyToEnumCase(DatasetSchema $schema, string $propertyName): ?string
    {
        foreach ($schema->byEnumCase as $enumCase => $descriptor) {
            if ($descriptor->propertyName === $propertyName) {
                return $enumCase;
            }
        }

        return null;
    }

    private function normaliseValue(mixed $value): mixed
    {
        return $value instanceof CarbonImmutable ? $value->toDateString() : $value;
    }
}
