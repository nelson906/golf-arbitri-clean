<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Assignment;
use Illuminate\Http\Request;

/**
 * PLACEHOLDER API Controller per Tournament
 * TODO: Implementare completamente quando necessario
 */
class TournamentApiController extends Controller
{
    public function icalFeed()
    {
        // TODO: Implementare feed iCal
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function jsonCalendar()
    {
        // TODO: Implementare calendario JSON
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function myAssignments()
    {
        // TODO: Implementare my assignments
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function myAvailabilities()
    {
        // TODO: Implementare my availabilities
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function declareAvailability(Tournament $tournament)
    {
        // TODO: Implementare declare availability
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function withdrawAvailability(Tournament $tournament)
    {
        // TODO: Implementare withdraw availability
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function store(Request $request)
    {
        // TODO: Implementare store
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function update(Request $request, Tournament $tournament)
    {
        // TODO: Implementare update
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function createAssignment(Request $request, Tournament $tournament)
    {
        // TODO: Implementare create assignment
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function destroyAssignment(Assignment $assignment)
    {
        // TODO: Implementare destroy assignment
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
