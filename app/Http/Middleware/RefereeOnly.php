<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefereeOnly
{
    /**
     * Handle an incoming request.
     *
     * Permette l'accesso solo agli utenti con ruolo Referee.
     * Reindirizza al login se non autenticato; abort 403 se autenticato ma non arbitro.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! auth()->user()->isReferee()) {
            abort(403, 'Accesso riservato agli arbitri.');
        }

        return $next($request);
    }
}
