<?php

namespace App\Http\Controllers;

use App\Enums\UserType;

class DashboardController extends Controller
{
    /**
     * Redirect to appropriate dashboard based on user role.
     *
     * This controller only handles routing - actual dashboard logic
     * is in the specific controllers:
     * - Admin\DashboardController for admin users
     * - User\DashboardController for referee/user types
     */
    public function index()
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // user_type è castato a enum UserType nel modello User.
        // Usare $user->user_type?->isAdmin() evita il confronto stringa/enum
        // che causava il fallthrough al default (referee.dashboard) per tutti gli admin.
        if ($user->user_type?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('referee.dashboard');
    }
}
