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

        if (!$user) {
            return redirect()->route('login');
        }

        // Verifica accesso ai documenti based on user type
        if ($user->user_type === 'referee') {
            // Referee può accedere solo a documenti pubblici o della sua zona
            $document = $request->route('document');
            if ($document && !$document->is_public && $document->zone_id !== $user->zone_id) {
                abort(403, 'Accesso negato a questo documento.');
            }
        }

        return $next($request);
    }
}
