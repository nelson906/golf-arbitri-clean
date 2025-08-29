<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Assignment::with(['user', 'tournament.club', 'tournament.zone', 'assignedBy'])
            ->when($request->filled('tournament_id'), function ($q) use ($request) {
                $q->where('tournament_id', $request->tournament_id);
            })
            ->when($request->filled('user_id'), function ($q) use ($request) {
                $q->where('user_id', $request->user_id);
            })
            ->when($request->filled('role'), function ($q) use ($request) {
                $q->where('role', $request->role);
            });

        // Zone filtering
        if (!in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->whereHas('tournament', function($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        $assignments = $query->orderByDesc('assigned_at')->paginate(20);

        return view('admin.assignments.index', compact('assignments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:' . implode(',', array_keys(Assignment::ROLES)),
            'notes' => 'nullable|string',
        ]);

        $tournament = Tournament::findOrFail($request->tournament_id);
        $referee = User::findOrFail($request->user_id);

        // Check if already assigned
        if (Assignment::where('tournament_id', $tournament->id)
                      ->where('user_id', $referee->id)
                      ->exists()) {
            return back()->with('error', 'Arbitro già assegnato a questo torneo');
        }

        Assignment::create([
            'tournament_id' => $tournament->id,
            'user_id' => $referee->id,
            'role' => $request->role,
            'notes' => $request->notes,
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
        ]);

        return back()->with('success', "Arbitro {$referee->name} assegnato come {$request->role}");
    }

    public function destroy(Assignment $assignment)
    {
        $assignment->delete();

        return back()->with('success', 'Assegnazione rimossa con successo');
    }
}