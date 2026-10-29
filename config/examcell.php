<?php

return ['restore_upload_max_kb' => (int) env('RESTORE_UPLOAD_MAX_KB', 524288), 'max_duties_per_day' => (int) env('MAX_DUTIES_PER_DAY', 2), 'cgpa_attempt_policy' => env('CGPA_ATTEMPT_POLICY', 'latest')];
