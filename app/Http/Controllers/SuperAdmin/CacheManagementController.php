<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\CacheService;
use Illuminate\Http\JsonResponse;
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
    public function clear(Request $request): JsonResponse
    {
        try {
            $types = ['application', 'config', 'route', 'view'];

        if ($request->has('types')) {
            $types = [];
            foreach ($request->array('types') as $voce) {
                if (is_string($voce)) {
                    $types[] = $voce;
                }
            }
        }
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
    public function optimize(Request $request): JsonResponse
    {
        try {
            $operations = ['config', 'route', 'view'];

        if ($request->has('operations')) {
            $operations = [];
            foreach ($request->array('operations') as $voce) {
                if (is_string($voce)) {
                    $operations[] = $voce;
                }
            }
        }
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
     * Pulisci cache applicazione
     */
    public function clearApplication(Request $request): JsonResponse
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
    public function clearViews(Request $request): JsonResponse
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
