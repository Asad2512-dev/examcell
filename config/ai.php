<?php

return [
    // The key is read only from OPENAI_API_KEY (config/openai.php) and is never displayed or logged.
    'model' => env('OPENAI_MODEL', 'gpt-4.1'),
    'models' => ['gpt-4.1', 'gpt-4.1-mini', 'gpt-4o', 'gpt-4o-mini'],
    'max_attempts' => 3,
    'retry_delay_ms' => (int) env('AI_RETRY_DELAY_MS', 800),
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 8000),
    // USD per 1M tokens [input, output]. Estimates for the usage screen; check OpenAI's pricing page.
    'pricing' => [
        'gpt-4.1' => [2.00, 8.00],
        'gpt-4.1-mini' => [0.40, 1.60],
        'gpt-4o' => [2.50, 10.00],
        'gpt-4o-mini' => [0.15, 0.60],
    ],
    'default_columns' => 6,
];
