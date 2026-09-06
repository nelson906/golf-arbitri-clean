<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOrSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $userType = $user->user_type;

        if (! $userType->isAdmin()) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
