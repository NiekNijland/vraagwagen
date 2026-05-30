<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * Why a question cannot be answered. Lets the UI show a tailored message and icon instead of one
 * generic "not supported" string, and lets the model say *why* rather than only *that* it refused.
 */
enum RefusalReason: string
{
    /**
     * Not a sincere question about the Dutch vehicle registry (off-topic, arithmetic, injection).
     */
    case OutOfScope = 'out_of_scope';

    /**
     * About vehicles, but the registry records no such field (driver, owner, price paid, mileage, theft).
     */
    case NoSuchData = 'no_such_data';

    /**
     * Answerable in principle, but the query is unbounded or the cross-dataset join exceeds the plate cap.
     */
    case TooBroad = 'too_broad';

    /**
     * Under-specified — the question needs more detail before a single query can answer it.
     */
    case Ambiguous = 'ambiguous';
}
