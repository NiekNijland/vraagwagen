<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * The deterministic combine operations a {@see Presentation} may request.
 * `GroupShare` works over a single grouped query (a group's share of the
 * column total); the rest combine two scalar query results.
 */
enum DeriveOp: string
{
    case GroupShare = 'groupShare';
    case Percentage = 'percentage';
    case Ratio = 'ratio';
    case Difference = 'difference';
    case Sum = 'sum';

    /**
     * Whether this op combines two scalar results (vs operating on one grouped
     * result like {@see self::GroupShare}).
     */
    public function isBinaryScalar(): bool
    {
        return $this !== self::GroupShare;
    }
}
