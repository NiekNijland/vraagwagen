<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

enum DeriveOp: string
{
    case GroupShare = 'groupShare';
    case Percentage = 'percentage';
    case Ratio = 'ratio';
    case Difference = 'difference';
    case Sum = 'sum';

    public function isBinaryScalar(): bool
    {
        return $this !== self::GroupShare;
    }
}
