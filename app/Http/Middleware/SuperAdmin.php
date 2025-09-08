<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->user_type !== 'super_admin') {
            abort(403, 'Accesso non autorizzato.');
        }

        return $next($request);
    }
}
