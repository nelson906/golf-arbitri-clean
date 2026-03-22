<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocumentAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Verifica accesso ai documenti based on user type
        if ($user->isReferee()) {
            // Referee può accedere solo a documenti pubblici o della sua zona.
            // Se il parametro di route 'document' è presente ma non è un'istanza di Document
            // (es. route binding fallito, ID non trovato) blocchiamo l'accesso per sicurezza
            // invece di lasciare passare silenziosamente.
            $document = $request->route('document');

            if ($document !== null && ! ($document instanceof \App\Models\Document)) {
                // Route binding non ha risolto un Document valido — nega l'accesso
                abort(403, 'Accesso negato a questo documento.');
            }

            if ($document instanceof \App\Models\Document && ! $document->is_public && $document->zone_id !== $user->zone_id) {
                abort(403, 'Accesso negato a questo documento.');
            }
        }

        return $next($request);
    }
}
