<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use BackedEnum;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use NiekNijland\RDW\Fields\RegisteredVehicleField;
use NiekNijland\RDW\Fields\RegisteredVehicleFuelField;
use NiekNijland\RDW\Schema\CastType;
use NiekNijland\RDW\Schema\SchemaRegistry;

/**
 * Casts a raw string value (as it left the LLM) into the typed shape PlanRunner needs for the
 * RDW PHP client's `where()` helpers. License plates take a different path so user input like
 * "GT-486-N" still matches the stored "GT486N".
 */
final readonly class FieldCaster
{
    private const string INTEGER_PATTERN = '/^-?\d+$/';

    private const array TRUE_LITERALS = ['true', '1', 'ja', 'yes'];

    private const array FALSE_LITERALS = ['false', '0', 'nee', 'no'];

    public function __construct(private SchemaRegistry $schemas)
    {
    }

    public function cast(BackedEnum $field, string $raw, TargetDataset $dataset): mixed
    {
        if ($field === RegisteredVehicleField::LicensePlate || $field === RegisteredVehicleFuelField::LicensePlate) {
            return PlateNormaliser::normalise($raw);
        }

        $cast = $this->schemas->get($dataset->datasetId())->byEnumCase[$field->name]->cast ?? null;
        if ($cast === null) {
            return $raw;
        }

        return match ($cast) {
            CastType::Boolean => $this->castBoolean($field, $raw),
            CastType::Integer => $this->castInteger($field, $raw),
            CastType::Decimal => is_numeric($raw) ? (float) $raw : $raw,
            CastType::CalendarDate, CastType::NumericDate => CarbonImmutable::parse($raw, 'UTC'),
            default => $raw,
        };
    }

    /**
     * @param list<string> $raws
     * @return list<mixed>
     */
    public function castMany(BackedEnum $field, array $raws, TargetDataset $dataset): array
    {
        return array_map(
            fn (string $v): mixed => $this->cast($field, $v, $dataset),
            $raws,
        );
    }

    private function castBoolean(BackedEnum $field, string $raw): bool
    {
        $normalised = strtolower(trim($raw));

        if (in_array($normalised, self::TRUE_LITERALS, true)) {
            return true;
        }

        if (in_array($normalised, self::FALSE_LITERALS, true)) {
            return false;
        }

        throw new InvalidArgumentException(sprintf(
            'Field "%s" requires a boolean value, got "%s".',
            $field->name,
            $raw,
        ));
    }

    private function castInteger(BackedEnum $field, string $raw): int
    {
        if (preg_match(self::INTEGER_PATTERN, $raw) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Field "%s" requires an integer value, got "%s".',
                $field->name,
                $raw,
            ));
        }

        return (int) $raw;
    }
}
