<?php

use App\Http\Middleware\AdminOrSuperAdmin;
use App\Http\Middleware\SuperAdmin;
use App\Http\Middleware\RefereeOrAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin_or_superadmin' => AdminOrSuperAdmin::class,
            'super_admin' => SuperAdmin::class,
            'referee_or_admin' => RefereeOrAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
