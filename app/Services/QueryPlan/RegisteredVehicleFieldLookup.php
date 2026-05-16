<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use NiekNijland\RDW\Fields\RegisteredVehicleField;

/**
 * O(1) lookup from PascalCase enum case name to {@see RegisteredVehicleField}.
 * The enum has ~50 cases; building the map once removes the per-clause linear
 * scan that PlanRunner used to do.
 */
final class RegisteredVehicleFieldLookup
{
    /** @var array<string, RegisteredVehicleField>|null */
    private static ?array $byName = null;

    public static function tryGet(string $name): ?RegisteredVehicleField
    {
        return self::map()[$name] ?? null;
    }

    /**
     * @return array<string, RegisteredVehicleField>
     */
    private static function map(): array
    {
        if (self::$byName === null) {
            $map = [];
            foreach (RegisteredVehicleField::cases() as $case) {
                $map[$case->name] = $case;
            }
            self::$byName = $map;
        }

        return self::$byName;
    }
}
