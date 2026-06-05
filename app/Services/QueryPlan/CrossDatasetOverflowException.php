<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

/**
 * Thrown when a cross-dataset plate lookup matches more vehicles than the {@see
 * StepReferenceResolver::LIST_LIMIT} cap, so the join cannot be completed without silently
 * truncating. Distinct from its parent so the caller can turn it into a {@see RefusalReason::TooBroad}
 * refusal ("narrow your question") rather than a generic failure.
 */
final class CrossDatasetOverflowException extends StepReferenceException
{
}
