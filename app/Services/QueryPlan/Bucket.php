<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

enum Bucket: string
{
    case None = 'none';
    case Year = 'year';
    case Month = 'month';
    case Day = 'day';
}
