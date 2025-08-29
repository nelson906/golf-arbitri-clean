<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display users (referees + admins)
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = User::with(['zone'])
            ->when($request->filled('user_type'), function ($q) use ($request) {
                $q->where('user_type', $request->user_type);
            })
            ->when($request->filled('level'), function ($q) use ($request) {
                $q->where('level', $request->level);
            })
            ->when($request->filled('zone_id'), function ($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('referee_code', 'like', "%{$search}%");
                });
            });

        // Zone filtering for non-super admins
        if (!in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->where('zone_id', $user->zone_id);
        }

        $users = $query->orderBy('name')->paginate(20);

        $zones = Zone::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'zones'));
    }

    /**
     * Show user details
     */
    public function show(User $user)
    {
        $user->load(['zone', 'assignments.tournament', 'availabilities.tournament', 'careerHistory']);

        // Stats summary
        $stats = [
            'total_assignments' => $user->assignments->count(),
            'current_year_assignments' => $user->assignments()
                ->whereHas('tournament', function($q) {
                    $q->whereYear('start_date', date('Y'));
                })->count(),
            'total_availabilities' => $user->availabilities->count(),
            'roles_summary' => $user->assignments->groupBy('role')->map->count(),
        ];

        return view('admin.users.show', compact('user', 'stats'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $zones = Zone::orderBy('name')->get();
        return view('admin.users.create', compact('zones'));
    }

    /**
     * Store new user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'user_type' => 'required|in:' . implode(',', array_keys(User::USER_TYPES)),
            'zone_id' => 'nullable|exists:zones,id',
            'level' => 'nullable|in:' . implode(',', array_keys(User::LEVELS)),
        ]);

        $userData = $request->only([
            'name', 'first_name', 'last_name', 'email', 'user_type',
            'referee_code', 'level', 'gender', 'zone_id', 'phone', 'city'
        ]);

        $userData['password'] = Hash::make($request->password ?? 'temp123');

        User::create($userData);

        return redirect()->route('admin.users.index')
            ->with('success', 'Utente creato con successo');
    }

    /**
     * Show edit form
     */
    public function edit(User $user)
    {
        $zones = Zone::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'zones'));
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'user_type' => 'required|in:' . implode(',', array_keys(User::USER_TYPES)),
        ]);

        $userData = $request->only([
            'name', 'first_name', 'last_name', 'email', 'user_type',
            'referee_code', 'level', 'gender', 'zone_id', 'phone', 'city',
            'is_active'
        ]);

        $user->update($userData);

        return redirect()->route('admin.users.index')
            ->with('success', 'Utente aggiornato con successo');
    }

    /**
     * Toggle user active status
     */
    public function toggleActive(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'attivato' : 'disattivato';

        return back()->with('success', "Utente {$status} con successo");
    }
}