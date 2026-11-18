<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveUser
{
    public function handle(Request $r, Closure $next)
    {
        if (! $r->user()?->active) {
            auth()->logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();
            abort(403, 'Account inactive. Contact the examination cell.');
        }

        return $next($r);
    }
}
