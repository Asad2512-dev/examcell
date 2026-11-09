<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
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

    /** Manual test identity switches emulate login’s password-hash session binding. */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);
        $this->withSession(['password_hash_'.($guard ?: config('auth.defaults.guard')) => $user->getAuthPassword()]);

        return $this;
    }

    /** Legacy screen URLs now redirect into workspaces; follow that one hop so older assertions still read the page. */
    public function get($uri, array $headers = [])
    {
        $response = parent::get($uri, $headers);
        if ($response->isRedirect() && preg_match('#^/(admin|teacher|student)/[a-z0-9-]+#', (string) $uri, $m) && str_contains((string) $response->headers->get('Location'), '/'.$m[1].'/')) {
            return parent::get($response->headers->get('Location'), $headers);
        }

        return $response;
    }
}
