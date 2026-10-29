<?php

use Monolog\Handler\StreamHandler;

return ['default' => 'single', 'channels' => ['single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log'), 'level' => 'debug'], 'stderr' => ['driver' => 'monolog', 'handler' => StreamHandler::class, 'with' => ['stream' => 'php://stderr']]]];
