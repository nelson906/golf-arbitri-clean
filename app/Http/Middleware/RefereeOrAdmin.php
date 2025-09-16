<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RefereeOrAdmin
{
    /**
     * Handle an incoming request for referee or admin access
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Authentication required',
                    'error' => 'Unauthenticated'
                ], 401);
            }

            return redirect()->guest(route('login'));
        }

        $user = Auth::user();
        $userType = $user->user_type;

        // Allowed user types for referee/admin access
        $allowedTypes = ['referee', 'admin', 'national_admin', 'super_admin'];

        if (!in_array($userType, $allowedTypes)) {
            // Log unauthorized access attempt
            Log::warning('Unauthorized referee/admin access attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_type' => $userType,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'requested_url' => $request->fullUrl(),
                'timestamp' => now(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied. Referee or administrator privileges required.',
                    'error' => 'Forbidden'
                ], 403);
            }

            abort(403, 'Accesso negato. Sono richiesti privilegi di arbitro o amministratore per accedere a questa sezione.');
        }

        // For referees accessing their own data
        if ($userType === 'referee') {
            $this->checkRefereeAccess($request, $user);
        }

        // For admins, apply zone restrictions
        if (in_array($userType, ['admin', 'national_admin']) && $user->zone_id) {
            $this->checkZoneAccess($request, $user);
        }

        // Log successful access
        Log::info('Referee/Admin access granted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_type' => $userType,
            'zone_id' => $user->zone_id,
            'requested_url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
        ]);

        return $next($request);
    }

    /**
     * Check referee access to their own data
     */
    private function checkRefereeAccess(Request $request, $user): void
    {
        $routeParameters = $request->route()->parameters();

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
     * Check zone-based access for admins
     */
    private function checkZoneAccess(Request $request, $user): void
    {
        $routeParameters = $request->route()->parameters();

        // Check for zone-specific resources
        foreach ($routeParameters as $key => $value) {
            if ($this->isZoneRestrictedResource($key, $value, $user)) {
                Log::warning('Zone access violation attempt', [
                    'user_id' => $user->id,
                    'user_zone_id' => $user->zone_id,
                    'requested_resource' => $key,
                    'resource_id' => $value,
                    'url' => $request->fullUrl(),
                ]);

                abort(403, 'Accesso negato. Non hai i permessi per accedere a risorse di altre zone.');
            }
        }
    }

    /**
     * Check if a referee is trying to access someone else's data
     */
    private function isRefereeRestrictedResource(string $parameterName, $resourceId, $user): bool
    {
        // Resources that referees should only access for themselves
        $refereeOwnResources = [
            'referee' => \App\Models\User::class,
            'availability' => \App\Models\Availability::class,
            'assignment' => \App\Models\Assignment::class,
            'application' => \App\Models\Application::class,
        ];

        if (!isset($refereeOwnResources[$parameterName])) {
            return false;
        }

        $modelClass = $refereeOwnResources[$parameterName];

        try {
            $resource = $modelClass::find($resourceId);

            if (!$resource) {
                return false; // Resource not found, let the controller handle it
            }

            // Check ownership based on resource type
            switch ($parameterName) {
                case 'referee':
                    return $resource->id !== $user->id;

                case 'availability':
                    return $resource->referee_id !== $user->id;

                case 'assignment':
                    return $resource->referee_id !== $user->id;

                case 'application':
                    return $resource->referee_id !== $user->id;

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

    /**
     * Check if a resource is zone-restricted for admins
     */
    private function isZoneRestrictedResource(string $parameterName, $resourceId, $user): bool
    {
        // Define which resources require zone checking for admins
        $zoneRestrictedResources = [
            'tournament' => \App\Models\Tournament::class,
            'referee' => \App\Models\User::class,
            'club' => \App\Models\Club::class,
            'letterhead' => \App\Models\Letterhead::class,
        ];

        if (!isset($zoneRestrictedResources[$parameterName])) {
            return false;
        }

        $modelClass = $zoneRestrictedResources[$parameterName];

        try {
            $resource = $modelClass::find($resourceId);

            if (!$resource) {
                return false; // Resource not found, let the controller handle it
            }

            // Check if resource belongs to user's zone
            if (isset($resource->zone_id) && $resource->zone_id !== $user->zone_id) {
                // Allow access to global resources (zone_id = null) for all admins
                if ($resource->zone_id === null) {
                    return false;
                }
                return true; // Access violation
            }

            // Special handling for users (referees)
            if ($parameterName === 'referee' &&
                $resource->user_type === 'referee' &&
                $resource->zone_id !== $user->zone_id) {
                return true;
            }

        } catch (\Exception $e) {
            Log::error('Error checking zone access', [
                'error' => $e->getMessage(),
                'parameter' => $parameterName,
                'resource_id' => $resourceId,
                'user_id' => $user->id,
            ]);
        }

        return false;
    }
}
