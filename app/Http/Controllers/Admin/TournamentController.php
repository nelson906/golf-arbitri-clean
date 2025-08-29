<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Club;
use App\Models\TournamentType;
use App\Models\Zone;
use App\Models\User;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Tournament::with(['club', 'tournamentType', 'zone', 'assignments.user'])
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->when($request->filled('zone_id'), function ($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            })
            ->when($request->filled('tournament_type_id'), function ($q) use ($request) {
                $q->where('tournament_type_id', $request->tournament_type_id);
            });

        // Zone filtering
        if (!in_array($user->user_type, ['super_admin', 'national_admin'])) {
            $query->where('zone_id', $user->zone_id);
        }

        $tournaments = $query->orderByDesc('start_date')->paginate(15);

        $zones = Zone::orderBy('name')->get();
        $tournamentTypes = TournamentType::active()->ordered()->get();

        return view('admin.tournaments.index', compact('tournaments', 'zones', 'tournamentTypes'));
    }

    public function show(Tournament $tournament)
    {
        $tournament->load([
            'club', 'tournamentType', 'zone', 'creator',
            'assignments.user.zone', 'availabilities.user'
        ]);

        return view('admin.tournaments.show', compact('tournament'));
    }

    public function create()
    {
        $user = auth()->user();

        $clubs = Club::active()->inZone($user->zone_id)->orderBy('name')->get();
        $tournamentTypes = TournamentType::active()->ordered()->get();
        $zones = Zone::orderBy('name')->get();

        return view('admin.tournaments.create', compact('clubs', 'tournamentTypes', 'zones'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'club_id' => 'required|exists:clubs,id',
            'tournament_type_id' => 'required|exists:tournament_types,id',
        ]);

        $tournamentData = $request->only([
            'name', 'start_date', 'end_date', 'availability_deadline',
            'club_id', 'tournament_type_id', 'zone_id', 'description', 'notes'
        ]);

        $tournamentData['created_by'] = auth()->id();
        $tournamentData['status'] = 'draft';

        $tournament = Tournament::create($tournamentData);

        return redirect()->route('admin.tournaments.show', $tournament)
            ->with('success', 'Torneo creato con successo');
    }

    public function assignmentsForm(Tournament $tournament)
    {
        $tournament->load(['assignments.user', 'availabilities.user']);

        // Available referees (con disponibilità)
        $availableReferees = User::referees()
            ->active()
            ->whereHas('availabilities', function($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })
            ->whereDoesntHave('assignments', function($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })
            ->with('zone')
            ->orderBy('name')
            ->get();

        // Other referees (senza disponibilità ma nella zona)
        $otherReferees = User::referees()
            ->active()
            ->where('zone_id', $tournament->zone_id)
            ->whereDoesntHave('availabilities', function($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })
            ->whereDoesntHave('assignments', function($q) use ($tournament) {
                $q->where('tournament_id', $tournament->id);
            })
            ->orderBy('name')
            ->get();

        return view('admin.tournaments.assignments', compact('tournament', 'availableReferees', 'otherReferees'));
    }
}