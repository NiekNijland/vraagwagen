<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

final class Derivation
{
    public function percentage(float $numerator, float $denominator): Derived
    {
        return new Derived(DeriveOp::Percentage, $this->quotient($numerator, $denominator), $numerator, $denominator);
    }

    public function ratio(float $numerator, float $denominator): Derived
    {
        return new Derived(DeriveOp::Ratio, $this->quotient($numerator, $denominator), $numerator, $denominator);
    }

    public function difference(float $a, float $b): Derived
    {
        return new Derived(DeriveOp::Difference, $a - $b, $a, $b);
    }

    public function sum(float $a, float $b): Derived
    {
        return new Derived(DeriveOp::Sum, $a + $b, $a, $b);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @throws DerivationException
     */
    public function groupShare(array $rows, string $labelColumn, string $value, string $countColumn): Derived
    {
        $total = 0.0;
        $part = null;

        foreach ($rows as $row) {
            $count = (float) ($row[$countColumn] ?? 0);
            $total += $count;

            if ((string) ($row[$labelColumn] ?? '') === $value) {
                $part = $count;
            }
        }

        if ($part === null) {
            throw new DerivationException(sprintf('No "%s" group matched value "%s".', $labelColumn, $value));
        }

        return new Derived(DeriveOp::GroupShare, $this->quotient($part, $total), $part, $total);
    }

    private function quotient(float $numerator, float $denominator): float
    {
        if ($denominator === 0.0) {
            throw new DerivationException('Cannot derive a figure with a zero denominator.');
        }

        return $numerator / $denominator;
    }
}
