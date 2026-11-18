<?php

use App\Http\Middleware\ActiveUser;
use App\Http\Middleware\RecoverySession;
use App\Http\Middleware\Role;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')->withMiddleware(function (Middleware $m) {
    $m->alias(['active' => ActiveUser::class, 'role' => Role::class, 'recovery.session' => RecoverySession::class]);
    $m->prependToPriorityList(AuthenticatesSessions::class, RecoverySession::class);
    $m->redirectGuestsTo('/sign-in');
    $m->redirectUsersTo('/workspace');
})->withExceptions(function (Exceptions $e) {
    $e->dontFlash(['app_password']);
})->create();
