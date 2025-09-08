<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Redirect to appropriate dashboard based on user role
     */
    public function index()
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Check user role/type and redirect accordingly
        switch ($user->user_type) {
            case 'super_admin':
            case 'admin':
                return redirect()->route('admin.dashboard');
                
            case 'referee':
            case 'user':
            default:
                // For regular users (referee type), show user dashboard
                return $this->userDashboard();
        }
    }

    /**
     * User (ex-referee) dashboard
     */
    private function userDashboard()
    {
        $user = auth()->user();
        
        // Get active communications for user's zone or global
        $activeCommunications = Communication::with(['author', 'zone'])
            ->where('status', 'published')
            ->where(function($q) use ($user) {
                $q->whereNull('zone_id') // Global communications
                  ->orWhere('zone_id', $user->zone_id); // User's zone communications
            })
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        // Get user stats
        $stats = [
            'availabilities_count' => $user->availabilities()->count(),
            'assignments_count' => $user->assignments()->count(),
            'upcoming_tournaments' => $user->assignments()
                ->whereHas('tournament', function ($query) {
                    $query->where('start_date', '>=', now());
                })
                ->with('tournament.club')
                ->latest()
                ->limit(5)
                ->get(),
            'recent_availabilities' => $user->availabilities()
                ->with('tournament')
                ->latest()
                ->limit(5)
                ->get(),
        ];

        return view('dashboard', compact('stats', 'activeCommunications'));
    }
}
