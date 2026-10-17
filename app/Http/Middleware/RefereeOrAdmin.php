<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Area utente (/user/...): ammessi arbitri e admin, cioe' ogni utente
 * collegato (tutti i tipi utente sono ammessi).
 *
 * Pulizia 2026-10-17: tolti i controlli che non scattavano mai — utente senza
 * tipo (la colonna e' obbligatoria) e "l'arbitro vede solo i suoi dati" su
 * parametri {referee}/{availability}/{assignment} che nessuna rotta /user ha.
 * Quale dato vede chi e' deciso da TournamentVisibility e dai controller.
 */
class RefereeOrAdmin
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Le rotte /user stanno gia' dietro 'auth': resta come difesa se una
        // rotta finisse fuori da quel gruppo
        if (! Auth::user() instanceof User) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Authentication required',
                    'error' => 'Unauthenticated',
                ], 401);
            }

            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
