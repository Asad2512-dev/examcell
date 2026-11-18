<?php

return ['name' => env('APP_NAME', 'ExamCell'), 'env' => env('APP_ENV', 'local'), 'debug' => (bool) env('APP_DEBUG', false), 'url' => env('APP_URL', 'http://localhost:8000'), 'timezone' => 'Asia/Karachi', 'locale' => 'en', 'fallback_locale' => 'en', 'faker_locale' => 'en_US', 'cipher' => 'AES-256-CBC', 'key' => env('APP_KEY'), 'previous_keys' => [], 'maintenance' => ['driver' => 'file', 'store' => 'file']];
