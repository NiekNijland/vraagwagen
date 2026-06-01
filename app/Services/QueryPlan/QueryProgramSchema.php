<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

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

        $refusal = $schema->object([
            'reason' => $schema->string()
                ->enum(array_map(static fn (RefusalReason $r): string => $r->value, RefusalReason::cases()))
                ->description('Why the question cannot be answered: out_of_scope (not about the Dutch vehicle registry), no_such_data (registry records no such field — driver, owner, price paid, mileage, theft), too_broad (unbounded query or a cross-dataset join over the 1000-plate cap), ambiguous (under-specified).')
                ->required(),
            'suggestions' => $schema->array()
                ->items($schema->string())
                ->description('1-3 concrete questions the registry CAN answer that are close to what the user asked, each a complete question in the system-prompt language. Empty only when nothing relevant is answerable.')
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
            'refusal' => $refusal
                ->nullable()
                ->description('Set ONLY when display is "unsupported": the machine reason and alternative questions. Null for every answerable question.')
                ->required(),
            'explanation' => $schema->string()
                ->description('One short sentence in the system-prompt language. For an answer, summarise it (never include computed numbers). For an unsupported question, state plainly why it cannot be answered.')
                ->required(),
            'followUps' => $schema->array()
                ->items($schema->string())
                ->description('2-3 natural next questions the user is likely to ask after seeing THIS answer, each a complete standalone question in the system-prompt language that the registry can answer. They must build directly on the same subject (e.g. for "How many Porsche 911s?": "How many Porsche 911s are electric?", "Porsche 911 registrations per year", "Average engine power of the Porsche 911"). Empty for an unsupported question (the refusal carries suggestions instead).')
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
