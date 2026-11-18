<?php

return ['openai' => ['key' => env('OPENAI_API_KEY'), 'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'), 'timeout' => 20, 'max_output_tokens' => 500, 'enabled' => (bool) env('OPENAI_ENABLED', false)]];
