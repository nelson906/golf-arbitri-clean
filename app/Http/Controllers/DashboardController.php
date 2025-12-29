<?php

namespace App\Http\Controllers;

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

        // Check user role/type and redirect accordingly
        switch ($user->user_type) {
            case 'super_admin':
            case 'national_admin':
            case 'admin':
                return redirect()->route('admin.dashboard');

            case 'referee':
            case 'user':
            default:
                // // For regular users (referee type), show user dashboard
                // return $this->userDashboard();
                // Redirect to the complete referee dashboard
                return redirect()->route('referee.dashboard');
        }
    }
}
