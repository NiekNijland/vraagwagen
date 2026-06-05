<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final class ValueAliases
{
    /**
     * Live RDW stores the pink colour as `ROSE`, while the package vocabulary exposes `ROZE`.
     * Normalise planner output to the stored value so generated queries match live data.
     */
    private const array REGISTERED_VEHICLES_WHERE_EQ_ALIASES = [
        'PrimaryColor' => [
            'ROZE' => 'ROSE',
        ],
        'SecondaryColor' => [
            'ROZE' => 'ROSE',
        ],
    ];

    private const array REGISTERED_VEHICLES_GROUP_SHARE_ALIASES = [
        'PrimaryColor' => [
            'ROZE' => 'ROSE',
        ],
        'SecondaryColor' => [
            'ROZE' => 'ROSE',
        ],
    ];

    public static function canonicalWhereValue(TargetDataset $dataset, string $field, WhereOp $op, string $value): string
    {
        if ($value === '' || $op !== WhereOp::Equals) {
            return $value;
        }

        return match ($dataset) {
            TargetDataset::RegisteredVehicles => self::REGISTERED_VEHICLES_WHERE_EQ_ALIASES[$field][$value] ?? $value,
            TargetDataset::RegisteredVehicleFuels => $value,
        };
    }

    public static function canonicalSelectorValue(TargetDataset $dataset, string $field, string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return match ($dataset) {
            TargetDataset::RegisteredVehicles => self::REGISTERED_VEHICLES_GROUP_SHARE_ALIASES[$field][$value] ?? $value,
            TargetDataset::RegisteredVehicleFuels => $value,
        };
    }
}
