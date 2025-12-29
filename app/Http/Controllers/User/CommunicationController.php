<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * CommunicationController - Visualizzazione comunicazioni per utenti (referee)
 */
class CommunicationController extends Controller
{
    /**
     * Display a listing of published communications for the user
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = Communication::with(['author', 'zone'])
            ->where('status', 'published')
            ->where(function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id)
                    ->orWhereNull('zone_id');
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $communications = $query->paginate(15);

        return view('user.communications.index', compact('communications'));
    }

    /**
     * Display the specified communication
     */
    public function show(Communication $communication): View
    {
        $user = Auth::user();

        if ($communication->status !== 'published') {
            abort(404);
        }

        if ($communication->zone_id && $communication->zone_id !== $user->zone_id) {
            abort(403, 'Comunicazione non disponibile per la tua zona.');
        }

        $communication->load(['author', 'zone']);

        return view('user.communications.show', compact('communication'));
    }
}
