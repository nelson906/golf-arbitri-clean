<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user === null || ! $user->isSuperAdmin()) {
            abort(403, 'Accesso non autorizzato.');
        }

        return $next($request);
    }
}
