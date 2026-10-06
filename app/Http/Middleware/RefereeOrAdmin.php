<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RefereeOrAdmin
{
    /**
     * Handle an incoming request for referee or admin access
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated.
        // NB: si legge l'utente PRIMA e si controlla quello, invece di
        // Auth::check(): il check non restringe il tipo di Auth::user(), che
        // resterebbe User|null per tutto il resto del metodo.
        $user = Auth::user();

        if (! $user instanceof User) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Authentication required',
                    'error' => 'Unauthenticated',
                ], 401);
            }

            return redirect()->guest(route('login'));
        }

        $userType = $user->user_type; // UserType enum or null

        // Allowed: any user with a valid user_type (all 4 enum values)
        if ($userType === null) {
            // Log unauthorized access attempt
            Log::warning('Unauthorized referee/admin access attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_type' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'requested_url' => $request->fullUrl(),
                'timestamp' => now(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied. Referee or administrator privileges required.',
                    'error' => 'Forbidden',
                ], 403);
            }

            abort(403, 'Accesso negato. Sono richiesti privilegi di arbitro o amministratore per accedere a questa sezione.');
        }

        // For referees accessing their own data
        if ($user->isReferee()) {
            $this->checkRefereeAccess($request, $user);
        }

        // NB (pulizia 2026-10-06): tolti il controllo di zona per gli admin
        // (agiva su parametri che le rotte protette da questo middleware non
        // hanno) e il log INFO scritto a ogni richiesta. La visibilita' dei
        // dati e' in TournamentVisibility.

        return $next($request);
    }

    /**
     * Check referee access to their own data
     *
     * @param  \App\Models\User  $user
     */
    private function checkRefereeAccess(Request $request, $user): void
    {
        $routeParameters = $request->route()?->parameters() ?? [];

        // Check if referee is trying to access their own data
        foreach ($routeParameters as $key => $value) {
            if ($this->isRefereeRestrictedResource($key, $value, $user)) {
                Log::warning('Referee access violation attempt', [
                    'user_id' => $user->id,
                    'requested_resource' => $key,
                    'resource_id' => $value,
                    'url' => $request->fullUrl(),
                ]);

                abort(403, 'Accesso negato. Puoi accedere solo ai tuoi dati personali.');
            }
        }
    }

    /**
     * Check if a referee is trying to access someone else's data
     *
     * @param  int|string  $resourceId
     * @param  \App\Models\User  $user
     */
    private function isRefereeRestrictedResource(string $parameterName, $resourceId, $user): bool
    {
        // Resources that referees should only access for themselves
        $refereeOwnResources = [
            'referee' => \App\Models\User::class,
            'availability' => \App\Models\Availability::class,
            'assignment' => \App\Models\Assignment::class,
        ];

        if (! isset($refereeOwnResources[$parameterName])) {
            return false;
        }

        $modelClass = $refereeOwnResources[$parameterName];

        try {
            /** @var \Illuminate\Database\Eloquent\Model|null $resource */
            $resource = $modelClass::find($resourceId);

            if (! $resource instanceof \Illuminate\Database\Eloquent\Model) {
                return false; // Resource not found, let the controller handle it
            }

            // Check ownership based on resource type
            switch ($parameterName) {
                case 'referee':
                    return $resource->getAttribute('id') !== $user->id;

                case 'availability':
                    return $resource->getAttribute('user_id') !== $user->id;

                case 'assignment':
                    return $resource->getAttribute('user_id') !== $user->id;

                default:
                    return false;
            }
        } catch (\Exception $e) {
            Log::error('Error checking referee access', [
                'error' => $e->getMessage(),
                'parameter' => $parameterName,
                'resource_id' => $resourceId,
                'user_id' => $user->id,
            ]);
        }

        return false;
    }

}
