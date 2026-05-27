<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * A reference from a dependent query's `where` value to an earlier query's
 * result, written by the model as the *whole* value `{{q1.Brand}}`.
 *
 * Resolved deterministically by {@see StepReferenceResolver} before the query
 * runs; the model emits the token and never sees the substituted value. Only a
 * value that is exactly one token is a reference — a literal that merely
 * contains braces is not, so a hostile value can't smuggle a partial reference.
 */
final readonly class StepReference
{
    /**
     * Whole-value grammar: `{{<queryId>.<FieldEnumCase>}}`. `queryId` is an
     * identifier (e.g. `q1`); `field` is a PascalCase RegisteredVehicleField
     * case name. Existence of both is validated downstream, not here.
     */
    private const string PATTERN = '/^\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\.\s*([A-Za-z][A-Za-z0-9_]*)\s*\}\}$/';

    public function __construct(
        public string $queryId,
        public string $field,
    ) {}

    /**
     * Parse a `where` value into a reference, or null when it is a plain literal.
     */
    public static function tryParse(string $value): ?self
    {
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            return null;
        }

        return new self($matches[1], $matches[2]);
    }

    public function token(): string
    {
        return sprintf('{{%s.%s}}', $this->queryId, $this->field);
    }
}
