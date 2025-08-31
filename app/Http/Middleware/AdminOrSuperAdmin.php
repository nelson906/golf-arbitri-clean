<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOrSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $userType = auth()->user()->user_type;

        if (!in_array($userType, ['super_admin', 'national_admin', 'admin'])) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
