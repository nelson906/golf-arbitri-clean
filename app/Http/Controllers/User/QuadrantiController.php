<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuadrantiController extends Controller
{
    /**
     * Display the Quadranti (Starting Times Simulator) interface
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('user.quadranti.index');
    }

    /**
     * Handle Excel file upload for player names
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            // Process the Excel file
            // This would need proper Excel parsing logic
            $data = [
                ['Atlete'],  // Female players
                ['Atleti']   // Male players
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get sunrise and sunset times based on geographic area
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCoordinates(Request $request)
    {
        $geoArea = $request->input('geo_area', 'CENTRO');
        $date = $request->input('start', date('d/m/Y'));

        // Coordinate geografiche approssimative per le diverse aree italiane
        $coordinates = [
            'NORD OVEST' => ['lat' => 45.4642, 'lon' => 9.1900],   // Milano
            'NORD' => ['lat' => 45.4408, 'lon' => 10.9936],        // Verona
            'NORD EST' => ['lat' => 45.4654, 'lon' => 13.4500],    // Trieste
            'CENTRO' => ['lat' => 41.9028, 'lon' => 12.4964],      // Roma
            'CENTRO SUD' => ['lat' => 40.8518, 'lon' => 14.2681],  // Napoli
            'SUD EST' => ['lat' => 41.1171, 'lon' => 16.8719],     // Bari
            'SUD OVEST' => ['lat' => 38.1157, 'lon' => 13.3615],   // Palermo
            'SARDEGNA' => ['lat' => 40.1209, 'lon' => 9.0129]      // Olbia
        ];

        $coord = $coordinates[$geoArea] ?? $coordinates['CENTRO'];
        
        try {
            // Converti la data dal formato italiano
            $dateParts = explode('/', $date);
            $timestamp = mktime(0, 0, 0, $dateParts[1], $dateParts[0], $dateParts[2]);
            
            // Calcola alba e tramonto (formula semplificata)
            $sunrise = date_sunrise($timestamp, SUNFUNCS_RET_STRING, $coord['lat'], $coord['lon'], 90, 1);
            $sunset = date_sunset($timestamp, SUNFUNCS_RET_STRING, $coord['lat'], $coord['lon'], 90, 1);

            return response()->json([
                'sunrise' => $sunrise ?: '06:30',
                'sunset' => $sunset ?: '18:30'
            ]);
        } catch (\Exception $e) {
            Log::error('Error calculating ephemeris data: ' . $e->getMessage());
            return response()->json([
                'sunrise' => '06:30',
                'sunset' => '18:30'
            ]);
        }
    }
}
