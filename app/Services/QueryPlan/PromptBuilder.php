<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use App\Enums\Locale;
use NiekNijland\RDW\Datasets\DatasetId;
use NiekNijland\RDW\Schema\CastType;
use NiekNijland\RDW\Schema\DatasetSchema;
use NiekNijland\RDW\Schema\FieldDescriptor;
use NiekNijland\RDW\Schema\SchemaRegistry;

final readonly class PromptBuilder
{
    public function __construct(private SchemaRegistry $schemas)
    {
    }

    public function systemPrompt(Locale $locale): string
    {
        $schema = $this->schemas->get(DatasetId::RegisteredVehicles);
        $fieldCatalog = $this->renderFieldCatalog($schema);
        $vocabulary = $this->renderVocabulary($schema);
        [$brandA, $brandB, $brandC] = $this->examplePicks($schema, 'Brand', 3);
        [$modelA, $modelB] = $this->examplePicks($schema, 'CommercialName', 2);
        [$colorA, $colorB] = $this->examplePicks($schema, 'PrimaryColor', 2);
        $explanationLanguage = match ($locale) {
            Locale::Dutch => 'Dutch',
            Locale::English => 'English',
        };

        return <<<PROMPT
You translate natural-language questions about Dutch vehicle data into a structured query plan against the RDW "registeredVehicles" dataset (Socrata dataset m9d7-ebf2). The plan you emit is executed verbatim against a typed PHP query builder.

# Available fields

Each line is `EnglishName (type): dutch_source_key`. Use the EnglishName in plans; the type tells you how to encode values.

{$fieldCatalog}

# Value vocabulary

Values are stored in UPPERCASE Dutch. Use these exact strings:

{$vocabulary}

# Operators

- `eq`, `neq`, `gt`, `gte`, `lt`, `lte` — exact comparison. Use UPPERCASE for Dutch values.
- `contains` — case-insensitive substring search (Socrata `contains()`).
- `startsWith` — case-sensitive prefix search (Socrata `starts_with()`). Encode the prefix in UPPERCASE.

## Choosing the operator for model names

CommercialName and other free-text model fields store specific variants ("AYGO", "AYGO X", "UP", "UP CROSS", "GOLF", "GOLF PLUS"). Users rarely mean one exact stored value — they mean the family.

**Default to `startsWith` for model names**, especially in counting and breakdown questions:

- "how many Toyota Aygos" → `CommercialName startsWith AYGO` (matches "AYGO", "AYGO X").
- "Volkswagen Ups" → `startsWith UP` (matches "UP", "UP!", "UP CROSS").
- "Golfs" → `startsWith GOLF` (matches "GOLF", "GOLF PLUS", "GOLF VARIANT").

Use `eq` on a model only when the user spells out a fully-qualified variant (e.g. "Golf GTI", or an explicitly quoted exact name). Use `contains` only when the user is clearly searching loosely.

Brand is usually exact: "Toyota" → `Brand eq TOYOTA`. The family rule applies to the model side.

# Display hints

- `count` — a single number ("how many X?"). One count aggregate, empty `groupBy`.
- `bars` — a grouped breakdown ("X per Y", "colors of …", "top N", "most common"). One `groupBy` + one count aggregate, sort `n desc`, `limit` 25 (or 1 for "most common").
- `table` — a list of rows ("show me 10 …"). `select` with a few fields, no aggregates.
- `record` — a single vehicle (e.g. license-plate lookup).

# Mixing fields with aggregates

When the plan has any aggregate, every field you want in the output must go in `groupBy`. Never put a plain field in `select` next to an aggregate — SoQL rejects it with "column not in group by". `select` is only for non-aggregated row queries.

# Dates

Date fields end in `*Date`. Pass `YYYY-MM-DD` strings. For "in 2017" emit two clauses: `gte 2017-01-01` AND `lt 2018-01-01`. For "in February 2017": `gte 2017-02-01` AND `lt 2017-03-01`.

# License plates

Plates are stored without separators ("1ZTZ08"); users will type dashes or spaces ("1-ZTZ-08", "1 ZTZ 08"). For a `LicensePlate` clause, strip all non-alphanumeric characters and uppercase the result. A full plate is unique — always use `eq`.

# Examples

User: How many Toyota Aygos are registered?
Plan:
  where: Brand eq TOYOTA, CommercialName startsWith AYGO
  aggregates: count(*) as n
  display: count

User: How many {$colorA} {$brandA} {$modelA}s from February 2017 are registered and insured?
Plan:
  where: Brand eq {$brandA}, CommercialName startsWith {$modelA}, PrimaryColor eq {$colorA}, IsWamInsured eq true, FirstAdmissionDate gte 2017-02-01, FirstAdmissionDate lt 2017-03-01
  aggregates: count(*) as n
  display: count

User: What colors of {$brandB} {$modelB} are registered, and how many per color?
Plan:
  where: Brand eq {$brandB}, CommercialName startsWith {$modelB}
  groupBy: PrimaryColor
  aggregates: count(*) as n
  orderBy: n desc
  limit: 25
  display: bars

User: Show me 10 {$colorB} {$brandC}s with their license plate, model and registration date
Plan:
  where: Brand eq {$brandC}, PrimaryColor eq {$colorB}
  select: LicensePlate, CommercialName, RegistrationDate
  orderBy: RegistrationDate desc
  limit: 10
  display: table

User: What's the most common {$brandA} variant from 1995?
Plan:
  where: Brand eq {$brandA}, FirstAdmissionDate gte 1995-01-01, FirstAdmissionDate lt 1996-01-01
  groupBy: CommercialName
  aggregates: count(*) as n
  orderBy: n desc
  limit: 1
  display: bars

# Output rules

- Fill every plan field; use empty arrays for parts that don't apply.
- Always set `limit`.
- `explanation` is one short sentence summarising the query, written in {$explanationLanguage}.
PROMPT;
    }

    private function renderFieldCatalog(DatasetSchema $schema): string
    {
        $lines = [];
        foreach ($schema->byEnumCase as $name => $descriptor) {
            $lines[] = sprintf('- %s (%s): %s', $name, $descriptor->cast->value, $descriptor->rdwKey);
        }

        return implode("\n", $lines);
    }

    /**
     * Pick the first $count values from a field's vocabulary, padding with the
     * first value when the vocabulary is shorter than requested. Returns a
     * fixed-length list so destructuring at the call site stays total.
     *
     * @return list<string>
     */
    private function examplePicks(DatasetSchema $schema, string $enumCase, int $count): array
    {
        $descriptor = $schema->byEnumCase[$enumCase] ?? null;
        $values = $descriptor !== null && $descriptor->vocabulary !== null
            ? $descriptor->vocabulary->values
            : [];

        if ($values === []) {
            return array_fill(0, $count, $enumCase);
        }

        $picks = array_slice($values, 0, $count);
        while (count($picks) < $count) {
            $picks[] = $values[0];
        }

        return $picks;
    }

    private function renderVocabulary(DatasetSchema $schema): string
    {
        $lines = [];
        foreach ($schema->fieldsWithVocabulary() as $field) {
            $vocabulary = $field->vocabulary;
            if ($vocabulary === null) {
                continue;
            }
            $values = implode(', ', $vocabulary->values);
            $lines[] = $vocabulary->exhaustive
                ? sprintf('- %s: one of %s', $field->enumCase, $values)
                : sprintf('- %s (examples — field is open): %s', $field->enumCase, $values);
        }

        $booleanFields = array_values(array_filter(
            $schema->exposedFields(),
            static fn (FieldDescriptor $f): bool => $f->cast === CastType::Boolean,
        ));
        if ($booleanFields !== []) {
            $names = implode(', ', array_map(static fn (FieldDescriptor $f): string => $f->enumCase, $booleanFields));
            $lines[] = sprintf('- boolean fields (%s): write "true" or "false" as the string value', $names);
        }

        return implode("\n", $lines);
    }
}
