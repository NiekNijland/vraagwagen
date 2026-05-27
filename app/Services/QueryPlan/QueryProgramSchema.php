<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Ai\Agents\QueryProgramAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * Builds the structured-output schema {@see QueryProgramAgent} exposes to the
 * model: an ordered list of sub-queries (each the full {@see PlanSchema} plus a
 * stable `id`) and a {@see Presentation}.
 *
 * The per-query shape is {@see PlanSchema::build()} verbatim, so the engine's
 * single-plan contract is reused untouched — `queries[]` just adds one nesting
 * level. References between queries ride inside `where` values as
 * `{{qID.FieldName}}` strings, so they add no schema complexity.
 */
final class QueryProgramSchema
{
    /**
     * @return array<string, Type>
     */
    public static function build(JsonSchema $schema): array
    {
        $queryItem = $schema->object([
            'id' => $schema->string()
                ->description('Stable id for this query, e.g. "q1". Referenced by later queries ({{q1.Brand}}) and by the presentation. Must match [A-Za-z_][A-Za-z0-9_]*.')
                ->required(),
            ...PlanSchema::build($schema),
        ]);

        $derive = $schema->object([
            'op' => $schema->string()
                ->enum(array_map(static fn (DeriveOp $o): string => $o->value, DeriveOp::cases()))
                ->description('How to combine results into one figure.')
                ->required(),
            'numerator' => $schema->string()
                ->description('For percentage/ratio/difference/sum: the query id of the numerator (a scalar query). Empty for groupShare.')
                ->required(),
            'denominator' => $schema->string()
                ->description('For percentage/ratio/difference/sum: the query id of the denominator (a scalar query). Empty for groupShare.')
                ->required(),
            'source' => $schema->string()
                ->description('For groupShare: the query id of the grouped query. Empty for the binary ops.')
                ->required(),
            'selectorColumn' => $schema->string()
                ->description('For groupShare: the group field whose share to compute, e.g. PrimaryColor.')
                ->required(),
            'selectorValue' => $schema->string()
                ->description('For groupShare: the exact group value to select, e.g. GEEL.')
                ->required(),
        ]);

        $presentation = $schema->object([
            'resultRef' => $schema->string()
                ->description('Which query id to display, or "derived" when "derive" is set.')
                ->required(),
            'display' => $schema->string()
                ->enum(array_map(static fn (DisplayHint $d): string => $d->value, DisplayHint::cases()))
                ->description('How to render the answer.')
                ->required(),
            'derive' => $derive
                ->nullable()
                ->description('Set to combine query results into one deterministic figure; null for a plain passthrough of resultRef.')
                ->required(),
            'explanation' => $schema->string()
                ->description('One short sentence summarising the answer, in the language specified by the system prompt. Never include computed numbers.')
                ->required(),
        ]);

        return [
            'queries' => $schema->array()
                ->description('Ordered list of 1-4 sub-queries to run. A later query may reference an earlier single-row query inside a where value with the whole value {{qID.FieldName}}.')
                ->items($queryItem)
                ->required(),
            'presentation' => $presentation->required(),
        ];
    }
}
