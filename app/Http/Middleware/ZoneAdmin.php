<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ZoneAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
public function handle(Request $request, Closure $next)
{
    $userType = auth()->user()->user_type ?? 'referee';

    if (!in_array($userType, ['zone_admin', 'admin', 'super_admin', 'national_admin'])) {
        abort(403);
    }

    return $next($request);
}}
