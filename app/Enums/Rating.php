<?php

declare(strict_types=1);

namespace App\Enums;

use App\Actions\Rdw\FindPopularQueries;
use App\Models\QueryRun;

/**
 * A user's thumbs up/down on a {@see QueryRun}. Drives the
 * "popular queries" ranking in {@see FindPopularQueries}.
 */
enum Rating: string
{
    case Up = 'up';
    case Down = 'down';
}
