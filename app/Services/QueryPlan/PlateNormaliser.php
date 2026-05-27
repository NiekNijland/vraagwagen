<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * Normalises a Dutch license plate to RDW's stored form: uppercase, no
 * separators (e.g. "1-ZTZ-08" / "1 ztz 08" → "1ZTZ08").
 *
 * Mirrors the frontend `detectPlate` normalisation in
 * resources/js/pages/query/plate.ts so a plate the user (or a dependent-step
 * reference) types with dashes still matches the case-sensitive, separator-less
 * `kenteken` column. This only strips and uppercases — it does not validate the
 * sidecode, so a non-plate value simply matches zero rows rather than throwing.
 */
final class PlateNormaliser
{
    public static function normalise(string $value): string
    {
        return strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $value));
    }
}
