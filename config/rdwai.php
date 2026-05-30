<?php

declare(strict_types=1);

return [
    'llm_model' => env('RDWAI_LLM_MODEL', 'gpt-4.1-mini'),
    'rdw_app_token' => env('RDW_APP_TOKEN'),
    'prompt' => [
        // Hard cap on the natural-language prompt the user submits, in characters. Bounds the
        // input side of every LLM call: 2000 chars ≈ 500 tokens is enough for a complex question,
        // small enough that a single request can't blow the per-day budget on its own.
        'max_length' => (int) env('RDWAI_MAX_PROMPT_LENGTH', 2000),
        'min_length' => 3,
    ],
    'rate_limit' => [
        'per_minute' => env('RDWAI_RATE_LIMIT_PER_MINUTE', 10),
        'per_day_ip' => env('RDWAI_RATE_LIMIT_PER_DAY_IP', 25),
        'per_day_global' => env('RDWAI_RATE_LIMIT_PER_DAY_GLOBAL', 50),
        'feedback_per_minute' => env('RDWAI_RATE_LIMIT_FEEDBACK_PER_MINUTE', 30),
    ],
    // USD per 1M tokens; bare keys match dated OpenAI variants as family prefixes.
    'model_prices' => [
        'gpt-4.1-nano' => [
            'input' => 0.10,
            'cached_input' => 0.025,
            'output' => 0.40,
        ],
        'gpt-4.1-mini' => [
            'input' => 0.40,
            'cached_input' => 0.10,
            'output' => 1.60,
        ],
        'gpt-4.1' => [
            'input' => 2.00,
            'cached_input' => 0.50,
            'output' => 8.00,
        ],
    ],
];
