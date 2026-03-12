<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ZoneAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $userType = auth()->user()->user_type;

        if (! ($userType?->isAdmin() ?? false)) {
            abort(403);
        }

        return $next($request);
    }
}
