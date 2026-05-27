<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use NiekNijland\RDW\Fields\RegisteredVehicleField;

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
