<?php

return ['default' => env('MAIL_MAILER', 'log'), 'mailers' => ['log' => ['transport' => 'log'], 'smtp' => ['transport' => 'smtp', 'host' => env('MAIL_HOST'), 'port' => env('MAIL_PORT', 587), 'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD'), 'scheme' => env('MAIL_SCHEME'), 'timeout' => 10, 'require_tls' => true]], 'from' => ['address' => env('MAIL_FROM_ADDRESS', 'examcell@example.invalid'), 'name' => env('MAIL_FROM_NAME', 'ExamCell')]];
