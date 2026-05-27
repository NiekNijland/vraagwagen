<?php

declare(strict_types=1);

namespace Tests\Unit\Services\QueryPlan;

use App\Services\QueryPlan\PlateNormaliser;
use PHPUnit\Framework\TestCase;

final class PlateNormaliserTest extends TestCase
{
    public function test_strips_separators_and_uppercases(): void
    {
        self::assertSame('1ZTZ08', PlateNormaliser::normalise('1-ZTZ-08'));
        self::assertSame('1ZTZ08', PlateNormaliser::normalise('1ztz08'));
        self::assertSame('1ZTZ08', PlateNormaliser::normalise('1 ZTZ 08'));
        self::assertSame('GT486N', PlateNormaliser::normalise('gt-486-n'));
    }

    public function test_leaves_an_already_normalised_plate_untouched(): void
    {
        self::assertSame('1ZTZ08', PlateNormaliser::normalise('1ZTZ08'));
    }

    public function test_empty_input_normalises_to_empty(): void
    {
        self::assertSame('', PlateNormaliser::normalise('---'));
        self::assertSame('', PlateNormaliser::normalise(''));
    }
}
