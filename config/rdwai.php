<?php

declare(strict_types=1);

return [
    'llm_model' => env('RDWAI_LLM_MODEL', 'gpt-4.1-nano'),
    'rdw_app_token' => env('RDW_APP_TOKEN'),
    'rate_limit' => [
        'per_minute' => env('RDWAI_RATE_LIMIT_PER_MINUTE', 10),
        'per_day_global' => env('RDWAI_RATE_LIMIT_PER_DAY_GLOBAL', 1000),
    ],
    'examples' => [
        'How many white Volkswagen Ups from February 2017 are registered and insured?',
        'What colors of Toyota Aygo are registered, and how many per color?',
        'Show me 10 red BMWs with their license plate, model and registration date',
        'How many electric Tesla Model 3 are insured in the Netherlands?',
    ],
];
