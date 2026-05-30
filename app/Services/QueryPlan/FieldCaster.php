<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use BackedEnum;
use Carbon\CarbonImmutable;
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
    public function __construct(private SchemaRegistry $schemas) {}

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
            CastType::Boolean => in_array(strtolower($raw), ['true', '1', 'ja', 'yes'], true),
            CastType::Integer => (int) $raw,
            CastType::Decimal => is_numeric($raw) ? (float) $raw : $raw,
            CastType::CalendarDate, CastType::NumericDate => CarbonImmutable::parse($raw, 'UTC'),
            default => $raw,
        };
    }

    /**
     * @param  list<string>  $raws
     * @return list<mixed>
     */
    public function castMany(BackedEnum $field, array $raws, TargetDataset $dataset): array
    {
        return array_map(
            fn (string $v): mixed => $this->cast($field, $v, $dataset),
            $raws,
        );
    }
}
