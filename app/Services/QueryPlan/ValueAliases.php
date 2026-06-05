<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final class ValueAliases
{
    private const string SCOPE_WHERE_EQ = 'where:eq';

    private const string SCOPE_GROUP_SHARE_SELECTOR = 'derive:groupShare:selector';

    /**
     * Live RDW stores the pink colour as `ROSE`, while the package vocabulary exposes `ROZE`.
     * Keep these live-data deviations in one scoped registry so new aliases can be added without
     * touching prompt text or spreading ad-hoc conditionals through planners and presenters.
     */
    private const array REGISTERED_VEHICLES_COLOUR_ALIASES = ['ROZE' => 'ROSE'];

    /** @var array<string, array<string, array<string, array<string, string>>>> */
    private const array ALIASES = [
        'RegisteredVehicles' => [
            self::SCOPE_WHERE_EQ => [
                'PrimaryColor' => self::REGISTERED_VEHICLES_COLOUR_ALIASES,
                'SecondaryColor' => self::REGISTERED_VEHICLES_COLOUR_ALIASES,
            ],
            self::SCOPE_GROUP_SHARE_SELECTOR => [
                'PrimaryColor' => self::REGISTERED_VEHICLES_COLOUR_ALIASES,
                'SecondaryColor' => self::REGISTERED_VEHICLES_COLOUR_ALIASES,
            ],
        ],
    ];

    public static function canonicalWhereValue(TargetDataset $dataset, string $field, WhereOp $op, string $value): string
    {
        if ($value === '' || $op !== WhereOp::Equals) {
            return $value;
        }

        return self::canonical(self::SCOPE_WHERE_EQ, $dataset, $field, $value);
    }

    public static function canonicalSelectorValue(TargetDataset $dataset, string $field, string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return self::canonical(self::SCOPE_GROUP_SHARE_SELECTOR, $dataset, $field, $value);
    }

    private static function canonical(string $scope, TargetDataset $dataset, string $field, string $value): string
    {
        return self::ALIASES[$dataset->value][$scope][$field][$value] ?? $value;
    }
}
