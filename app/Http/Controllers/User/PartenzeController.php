<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Support\PartenzeSsoToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * GET /user/partenze — apre il Simulatore Tempi Partenza (app Schema Partenze)
 * con l'utente gia' collegato, tramite un link firmato valido pochi secondi.
 *
 * Il simulatore vive solo in Schema Partenze: qui resta la voce di menu.
 */
class PartenzeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $url    = config('services.partenze.url');
        $secret = config('services.partenze.sso_secret');
        if (! is_string($url) || $url === '' || ! is_string($secret) || $secret === '') {
            abort(404);
        }

        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $token = PartenzeSsoToken::make(
            (string) $user->email,
            (string) $user->name,
            time(),
            Str::random(32),
            $secret,
        );

        return redirect()->away(rtrim($url, '/').'/sso?token='.urlencode($token));
    }
}
