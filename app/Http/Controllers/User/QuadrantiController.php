<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
            $uploadedFile = $request->file('file');
            
            // Carica il file Excel
            $spreadsheet = IOFactory::load($uploadedFile->getPathname());
            
            // Array per memorizzare i dati
            $atlete = [];
            $atleti = [];
            
            // Cerca il foglio "Atlete"
            if ($spreadsheet->sheetNameExists('Atlete')) {
                $worksheet = $spreadsheet->getSheetByName('Atlete');
                $atlete = $this->extractNamesFromWorksheet($worksheet);
            }
            
            // Cerca il foglio "Atleti"
            if ($spreadsheet->sheetNameExists('Atleti')) {
                $worksheet = $spreadsheet->getSheetByName('Atleti');
                $atleti = $this->extractNamesFromWorksheet($worksheet);
            }
            
            // Se non ci sono fogli con nomi specifici, prova con i primi due fogli
            if (empty($atlete) && empty($atleti)) {
                $sheetCount = $spreadsheet->getSheetCount();
                if ($sheetCount >= 1) {
                    $worksheet = $spreadsheet->getSheet(0);
                    $atleti = $this->extractNamesFromWorksheet($worksheet);
                }
                if ($sheetCount >= 2) {
                    $worksheet = $spreadsheet->getSheet(1);
                    $atlete = $this->extractNamesFromWorksheet($worksheet);
                }
            }
            
            // Restituisci i dati nel formato atteso dal JavaScript
            return response()->json([
                $atlete,  // Array 0: giocatrici
                $atleti   // Array 1: giocatori
            ]);
        } catch (\Exception $e) {
            Log::error('Error processing Excel file: ' . $e->getMessage());
            return response()->json(['error' => 'Errore nel caricamento del file Excel'], 500);
        }
    }
    
    /**
     * Estrae i nomi dal foglio di lavoro
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet
     * @return array
     */
    private function extractNamesFromWorksheet($worksheet)
    {
        $names = [];
        $highestRow = $worksheet->getHighestRow();
        
        // Inizia dalla riga 2 (assumendo che la riga 1 contenga le intestazioni)
        for ($row = 2; $row <= $highestRow; $row++) {
            // Prova prima la colonna B (Nome), poi la colonna A
            $name = $worksheet->getCell('B' . $row)->getValue();
            
            // Se la colonna B è vuota, prova la colonna A
            if (empty($name)) {
                $name = $worksheet->getCell('A' . $row)->getValue();
            }
            
            // Pulisci e aggiungi il nome se non è vuoto
            if (!empty($name)) {
                $name = trim($name);
                // Rimuovi eventuali numeri all'inizio (es. "1. NOME" diventa "NOME")
                $name = preg_replace('/^\d+\.?\s*/', '', $name);
                if (!empty($name)) {
                    $names[] = $name;
                }
            }
        }
        
        return $names;
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
            if (count($dateParts) !== 3) {
                throw new \Exception('Invalid date format');
            }
            
            // Crea un oggetto DateTime
            $dateTime = new \DateTime();
            $dateTime->setDate($dateParts[2], $dateParts[1], $dateParts[0]);
            $dateTime->setTime(12, 0, 0); // Mezzogiorno per il calcolo
            
            // Calcola alba e tramonto usando una formula approssimata
            $dayOfYear = $dateTime->format('z') + 1;
            $lat = deg2rad($coord['lat']);
            
            // Declinazione solare approssimata
            $P = asin(0.39795 * cos(0.98563 * ($dayOfYear - 173) * pi() / 180));
            
            // Angolo orario del sole
            $sunrise_angle = acos(-tan($P) * tan($lat));
            $sunset_angle = -$sunrise_angle;
            
            // Converti in ore
            $sunrise_hours = 12 - $sunrise_angle * 12 / pi();
            $sunset_hours = 12 - $sunset_angle * 12 / pi();
            
            // Aggiungi correzione per longitudine (15° = 1 ora)
            $longitude_correction = ($coord['lon'] - 15) / 15;
            
            // Considera anche l'ora legale se applicabile
            $dst = $dateTime->format('I'); // 1 se ora legale, 0 altrimenti
            
            $sunrise_hours += $longitude_correction - $dst;
            $sunset_hours += $longitude_correction - $dst;
            
            // Formatta i risultati
            $sunrise_h = floor($sunrise_hours);
            $sunrise_m = round(($sunrise_hours - $sunrise_h) * 60);
            $sunset_h = floor($sunset_hours);
            $sunset_m = round(($sunset_hours - $sunset_h) * 60);
            
            $sunrise = sprintf('%02d:%02d', $sunrise_h, $sunrise_m);
            $sunset = sprintf('%02d:%02d', $sunset_h, $sunset_m);

            return response()->json([
                'sunrise' => $sunrise,
                'sunset' => $sunset
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
