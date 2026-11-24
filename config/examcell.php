<?php

return [
    'restore_upload_max_kb' => (int) env('RESTORE_UPLOAD_MAX_KB', 524288),
    'max_duties_per_day' => (int) env('MAX_DUTIES_PER_DAY', 2),
    'cgpa_attempt_policy' => env('CGPA_ATTEMPT_POLICY', 'latest'),
    // Demo convenience only. Production stays locked down unless explicitly opted in.
    'quick_role_login' => filter_var(
        env('EXAMCELL_QUICK_ROLE_LOGIN', env('APP_ENV', 'production') === 'local'),
        FILTER_VALIDATE_BOOL,
    ),
    'demo_password' => env('EXAMCELL_DEMO_PASSWORD', '12345678'),
];
