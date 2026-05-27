<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final readonly class StepReference
{
    /** Whole-value grammar `{{<queryId>.<FieldEnumCase>}}`; existence validated downstream. */
    private const string PATTERN = '/^\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\.\s*([A-Za-z][A-Za-z0-9_]*)\s*\}\}$/';

    public function __construct(
        public string $queryId,
        public string $field,
    ) {
    }

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
