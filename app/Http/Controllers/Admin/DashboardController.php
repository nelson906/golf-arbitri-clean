<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

    $stats = [
        'total_users' => User::count(),
        'total_referees' => User::where('user_type', 'referee')->count(),
        'total_tournaments' => Tournament::count(),
        'recent_assignments' => Assignment::with(['user', 'tournament'])
            ->latest()
            ->limit(5)
            ->get(),
    ];

        return view('admin.dashboard', compact('stats'));
    }

}
