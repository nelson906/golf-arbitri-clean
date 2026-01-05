<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CacheManagementController extends Controller
{
    public function __construct(
        protected CacheService $cacheService
    ) {}

    /**
     * Pulisci cache sistema
     */
    public function clear(Request $request)
    {
        try {
            $types = $request->get('types', ['application', 'config', 'route', 'view']);
            $results = $this->cacheService->clearCache($types);

            return response()->json([
                'status' => 'success',
                'message' => 'Cache pulita con successo',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Errore pulizia cache', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Errore durante la pulizia della cache: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ottimizza sistema
     */
    public function optimize(Request $request)
    {
        try {
            $operations = $request->get('operations', ['config', 'route', 'view']);
            $results = $this->cacheService->optimize($operations);

            return response()->json([
                'status' => 'success',
                'message' => 'Sistema ottimizzato con successo',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Errore ottimizzazione sistema', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Errore durante l\'ottimizzazione: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Statistiche cache
     */
    public function stats(Request $request)
    {
        $stats = $this->cacheService->getCacheStats();

        if ($request->wantsJson()) {
            return response()->json($stats);
        }

        return view('super-admin.monitoring.cache-stats', compact('stats'));
    }

    /**
     * Pulisci cache applicazione
     */
    public function clearApplication(Request $request)
    {
        try {
            $results = $this->cacheService->clearCache(['application']);

            return response()->json([
                'status' => 'success',
                'message' => 'Cache applicazione pulita',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pulisci cache view
     */
    public function clearViews(Request $request)
    {
        try {
            $results = $this->cacheService->clearCache(['view']);

            return response()->json([
                'status' => 'success',
                'message' => 'Cache view pulita',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
