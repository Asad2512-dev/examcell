<?php

use App\Http\Middleware\ActiveUser;
use App\Http\Middleware\Role;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')->withMiddleware(function (Middleware $m) {
    $m->alias(['active' => ActiveUser::class, 'role' => Role::class]);
    $m->redirectGuestsTo('/sign-in');
})->withExceptions(function (Exceptions $e) {})->create();
