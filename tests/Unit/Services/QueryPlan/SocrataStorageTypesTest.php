<?php

declare(strict_types=1);

namespace Tests\Unit\Services\QueryPlan;

use App\Services\QueryPlan\SocrataStorageTypes;
use App\Services\QueryPlan\TargetDataset;
use NiekNijland\RDW\Schema\SchemaRegistry;
use PHPUnit\Framework\TestCase;

final class SocrataStorageTypesTest extends TestCase
{
    public function test_marks_known_text_stored_decimal_fuel_columns_as_needing_a_wrap(): void
    {
        $service = $this->service();

        // `nettomaximumvermogen` is stored as text in Socrata but cast Decimal in the package —
        // comparisons must cast with ::number or they sort lexicographically.
        self::assertTrue($service->needsNumericWrap(TargetDataset::RegisteredVehicleFuels, 'nettomaximumvermogen'));
        self::assertTrue($service->needsNumericWrap(TargetDataset::RegisteredVehicleFuels, 'co2_uitstoot_gecombineerd'));
        self::assertTrue($service->needsNumericWrap(TargetDataset::RegisteredVehicleFuels, 'brandstofverbruik_gecombineerd'));
    }

    public function test_does_not_wrap_real_number_columns_even_when_cast_decimal(): void
    {
        $service = $this->service();

        // WLTP fields are stored as `number` in Socrata — wrapping them would be wasted work.
        self::assertFalse($service->needsNumericWrap(TargetDataset::RegisteredVehicleFuels, 'emissie_co2_gecombineerd_wltp'));
        self::assertFalse($service->needsNumericWrap(TargetDataset::RegisteredVehicleFuels, 'actieradius'));
        // Re-classified by RDW from text to number; the old hardcoded list still wrapped this one.
        self::assertFalse($service->needsNumericWrap(TargetDataset::RegisteredVehicleFuels, 'brandstofverbruik_gewogen_gecombineerd'));
    }

    public function test_never_wraps_text_columns_with_a_non_numeric_cast(): void
    {
        $service = $this->service();

        // License plate is text storage AND text cast — wrap would corrupt comparisons.
        self::assertFalse($service->needsNumericWrap(TargetDataset::RegisteredVehicleFuels, 'kenteken'));
        self::assertFalse($service->needsNumericWrap(TargetDataset::RegisteredVehicleFuels, 'brandstof_omschrijving'));
    }

    public function test_returns_false_for_unknown_fields_without_throwing(): void
    {
        $service = $this->service();

        self::assertFalse($service->needsNumericWrap(TargetDataset::RegisteredVehicleFuels, 'this_field_does_not_exist'));
    }

    public function test_vehicles_dataset_has_no_text_stored_numerics(): void
    {
        // RegisteredVehicles uses `number` for every numeric column, so the wrap path stays cold.
        self::assertSame([], $this->service()->textStoredNumericKeys(TargetDataset::RegisteredVehicles));
    }

    public function test_full_text_stored_set_matches_socrata_metadata_for_fuels(): void
    {
        // Pinning the derived set: if RDW promotes a column from text to number (or vice versa),
        // this test fails so we notice instead of silently producing wrong comparisons.
        $expected = [
            'brandstofverbruik_gecombineerd',
            'co2_uitstoot_gecombineerd',
            'co2_uitstoot_gewogen',
            'geluidsniveau_rijdend',
            'geluidsniveau_stationair',
            'nettomaximumvermogen',
            'nominaal_continu_maximumvermogen',
            'toerental_geluidsniveau',
            'uitstoot_deeltjes_licht',
            'uitstoot_deeltjes_zwaar',
        ];

        $actual = $this->service()->textStoredNumericKeys(TargetDataset::RegisteredVehicleFuels);
        sort($actual);
        sort($expected);

        self::assertSame($expected, $actual);
    }

    private function service(): SocrataStorageTypes
    {
        return new SocrataStorageTypes(new SchemaRegistry());
    }
}
