<?php

declare(strict_types=1);

namespace App\Services\QueryPlan;

use Laravel\Ai\Responses\Data\Usage;

final readonly class CostEstimator
{
    private const float RATE_DIVISOR = 1_000_000.0;

    /**
     * @param array<string, array{input?: float|int, cached_input?: float|int, output?: float|int}> $prices
     */
    public function __construct(private array $prices)
    {
    }

    public function estimate(string $model, Usage $usage): ?float
    {
        $rates = $this->resolveRates($model);
        if ($rates === null) {
            return null;
        }

        $cacheRead = $usage->cacheReadInputTokens;
        $freshPromptTokens = $usage->promptTokens;

        $inputRate = (float) ($rates['input'] ?? 0);
        // Fall back to the input rate when no cache-read rate is declared.
        $cachedRate = isset($rates['cached_input']) ? (float) $rates['cached_input'] : $inputRate;
        $outputRate = (float) ($rates['output'] ?? 0);

        // Reasoning tokens are billed at the output rate (0 for non-reasoning models).
        $outputTokens = $usage->completionTokens + $usage->reasoningTokens;

        $cost = ($freshPromptTokens * $inputRate)
            + ($cacheRead * $cachedRate)
            + ($outputTokens * $outputRate);

        return $cost / self::RATE_DIVISOR;
    }

    /**
     * @return array{input?: float|int, cached_input?: float|int, output?: float|int}|null
     */
    private function resolveRates(string $model): ?array
    {
        if (isset($this->prices[$model])) {
            return $this->prices[$model];
        }

        $bestKey = null;
        foreach (array_keys($this->prices) as $key) {
            // Require a `-` boundary so `gpt-4` matches `gpt-4-...` but not `gpt-4o-...`.
            if (! str_starts_with($model, $key . '-')) {
                continue;
            }
            if ($bestKey === null || strlen($key) > strlen($bestKey)) {
                $bestKey = $key;
            }
        }

        return $bestKey === null ? null : $this->prices[$bestKey];
    }
}
