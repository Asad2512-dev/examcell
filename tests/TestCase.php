<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        if (config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_test')) {
            throw new \RuntimeException('Refusing destructive tests: use a dedicated MySQL database ending in _test.');
        }

        Carbon::setTestNow(Carbon::parse('2026-10-12 08:00:00', 'Asia/Karachi'));

        return $app;
    }
}
