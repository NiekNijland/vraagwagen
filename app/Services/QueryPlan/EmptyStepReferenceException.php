<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use RuntimeException;

/**
 * A step reference resolved against a lookup that matched no vehicles (or only null values).
 * Unlike {@see StepReferenceException} this is not a malformed program — the question was fine,
 * the registry simply holds nothing for it — so it degrades to a "no matches" refusal instead
 * of the "try rephrasing" error path.
 */
final class EmptyStepReferenceException extends RuntimeException {}
