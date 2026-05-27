<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use RuntimeException;

/**
 * Thrown when a {@see StepReference} cannot be resolved at runtime — the
 * referenced query is missing, did not return exactly one row (e.g. an unknown
 * plate yields zero), or lacks the referenced column.
 *
 * Like {@see DerivationException} this is a *data* outcome, not a malformed
 * plan: the action turns it into a graceful `unsupported` answer (or, later, a
 * bounded self-correction retry), never a raw 500.
 */
final class StepReferenceException extends RuntimeException {}
