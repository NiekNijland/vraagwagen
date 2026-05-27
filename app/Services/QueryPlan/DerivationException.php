<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use RuntimeException;

/**
 * Thrown when a {@see Derivation} cannot produce a figure from the data it was
 * given — a division by zero, or a grouped result whose selector matches no
 * row. Distinct from {@see \InvalidArgumentException} (a malformed plan, → 422)
 * because this is a data outcome the action turns into a graceful `unsupported`
 * answer, not a client error.
 */
final class DerivationException extends RuntimeException {}
