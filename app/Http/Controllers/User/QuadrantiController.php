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
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
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
                $atleti,   // Array 1: giocatori
            ]);
        } catch (\Exception $e) {
            Log::error('Error processing Excel file: '.$e->getMessage());

            return response()->json(['error' => 'Errore nel caricamento del file Excel'], 500);
        }
    }

    /**
     * Estrae i nomi dal foglio di lavoro
     *
     * @param  \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet  $worksheet
     * @return array
     */
    private function extractNamesFromWorksheet($worksheet)
    {
        $names = [];
        $highestRow = $worksheet->getHighestRow();

        // Inizia dalla riga 2 (assumendo che la riga 1 contenga le intestazioni)
        for ($row = 2; $row <= $highestRow; $row++) {
            // Prova prima la colonna B (Nome), poi la colonna A
            $name = $worksheet->getCell('B'.$row)->getValue();

            // Se la colonna B è vuota, prova la colonna A
            if (empty($name)) {
                $name = $worksheet->getCell('A'.$row)->getValue();
            }

            // Pulisci e aggiungi il nome se non è vuoto
            if (! empty($name)) {
                $name = trim($name);
                // Rimuovi eventuali numeri all'inizio (es. "1. NOME" diventa "NOME")
                $name = preg_replace('/^\d+\.?\s*/', '', $name);
                if (! empty($name)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * Get sunrise and sunset times based on geographic area
     *
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
            'SARDEGNA' => ['lat' => 40.1209, 'lon' => 9.0129],      // Olbia
        ];

        $coord = $coordinates[$geoArea] ?? $coordinates['CENTRO'];

        Log::info('Calcolo alba/tramonto', [
            'geo_area' => $geoArea,
            'date' => $date,
            'coordinates' => $coord,
        ]);

        try {
            // Converti la data dal formato italiano (potrebbe arrivare con / o -)
            $dateParts = preg_split('/[\/\-]/', $date);
            if ($dateParts === false || count($dateParts) !== 3) {
                throw new \Exception('Invalid date format: '.$date);
            }

            // Estrai giorno, mese e anno
            $giorno = intval($dateParts[0]);
            $mese = intval($dateParts[1]);
            $anno = intval($dateParts[2]);

            // Valida i valori
            if ($giorno < 1 || $giorno > 31 || $mese < 1 || $mese > 12 || $anno < 2000 || $anno > 2100) {
                throw new \Exception('Invalid date values: '.$date);
            }

            // Crea un oggetto DateTime
            $dateTime = new \DateTime;
            $dateTime->setDate($anno, $mese, $giorno);
            $dateTime->setTime(12, 0, 0);

            // Calcola il numero di giorni dall'inizio dell'anno
            $dayOfYear = intval($dateTime->format('z')) + 1; // +1 perché format('z') parte da 0

            // Latitudine e longitudine
            $lat = $coord['lat'];
            $lon = $coord['lon'];

            // Numero di giorni dal 1 gennaio 2000
            $n = $dateTime->diff(new \DateTime('2000-01-01'))->days;

            // Media longitudine del sole (in gradi)
            $L = fmod(280.460 + 0.9856474 * $n, 360);

            // Media anomalia del sole (in gradi)
            $g = fmod(357.528 + 0.9856003 * $n, 360);

            // Longitudine eclittica del sole
            $lambda = $L + 1.915 * sin(deg2rad($g)) + 0.020 * sin(deg2rad(2 * $g));

            // Obliquità dell'eclittica
            $epsilon = 23.439 - 0.0000004 * $n;

            // Declinazione del sole (in gradi)
            $delta = rad2deg(asin(sin(deg2rad($epsilon)) * sin(deg2rad($lambda))));

            // Equazione del tempo (in ore)
            $E = -1.915 * sin(deg2rad($g)) - 0.020 * sin(deg2rad(2 * $g)) + 2.466 * sin(deg2rad(2 * $lambda)) - 0.053 * sin(deg2rad(4 * $lambda));
            $E = $E * 4 / 60; // converti da gradi a ore

            // Calcola l'angolo orario del sole all'alba/tramonto (in gradi)
            // Usa -0.833° per considerare rifrazione + diametro solare
            $cosH = (sin(deg2rad(-0.833)) - sin(deg2rad($lat)) * sin(deg2rad($delta))) / (cos(deg2rad($lat)) * cos(deg2rad($delta)));

            // Gestisci i casi estremi (sole sempre sopra o sotto l'orizzonte)
            if ($cosH < -1) {
                // Sole sempre sopra l'orizzonte (giorno polare)
                return response()->json([
                    'sunrise' => '00:00',
                    'sunset' => '23:59',
                ]);
            } elseif ($cosH > 1) {
                // Sole sempre sotto l'orizzonte (notte polare)
                return response()->json([
                    'sunrise' => '--:--',
                    'sunset' => '--:--',
                ]);
            }

            // Calcola l'angolo orario (in gradi, poi converti in ore)
            $H = rad2deg(acos($cosH));

            // Calcola ora locale del mezzogiorno solare
            $transit = 12 - $E - ($lon / 15);

            // Calcola alba e tramonto
            $sunrise_local = $transit - ($H / 15);
            $sunset_local = $transit + ($H / 15);

            // Determina se è in vigore l'ora legale (ultima domenica marzo - ultima domenica ottobre)
            $timezone_offset = 1; // CET (ora solare)

            // Calcola l'inizio e la fine dell'ora legale per l'anno corrente
            // Ora legale: ultima domenica di marzo alle 2:00 -> ultima domenica di ottobre alle 3:00
            $lastSundayMarch = new \DateTime("last sunday of march $anno");
            $lastSundayOctober = new \DateTime("last sunday of october $anno");

            if ($dateTime >= $lastSundayMarch && $dateTime < $lastSundayOctober) {
                $timezone_offset = 2; // CEST (ora legale)
            }

            // Applica il fuso orario (rifrazione già inclusa nel calcolo con -0.833°)
            $sunrise_final = $sunrise_local + $timezone_offset;
            $sunset_final = $sunset_local + $timezone_offset;

            // Formatta i risultati
            $sunrise_h = floor($sunrise_final);
            $sunrise_m = round(($sunrise_final - $sunrise_h) * 60);
            $sunset_h = floor($sunset_final);
            $sunset_m = round(($sunset_final - $sunset_h) * 60);

            // Gestisci i minuti che vanno oltre 60
            if ($sunrise_m >= 60) {
                $sunrise_h++;
                $sunrise_m -= 60;
            }
            if ($sunset_m >= 60) {
                $sunset_h++;
                $sunset_m -= 60;
            }

            $sunrise = sprintf('%02d:%02d', $sunrise_h, $sunrise_m);
            $sunset = sprintf('%02d:%02d', $sunset_h, $sunset_m);

            Log::info('Risultati calcolo alba/tramonto', [
                'dayOfYear' => $dayOfYear,
                'declinazione' => $delta,
                'H' => $H,
                'equation_of_time' => $E,
                'transit' => $transit,
                'sunrise_local' => $sunrise_local,
                'sunset_local' => $sunset_local,
                'timezone_offset' => $timezone_offset,
                'sunrise' => $sunrise,
                'sunset' => $sunset,
            ]);

            return response()->json([
                'sunrise' => $sunrise,
                'sunset' => $sunset,
            ]);
        } catch (\Exception $e) {
            Log::error('Error calculating ephemeris data: '.$e->getMessage());

            return response()->json([
                'sunrise' => '06:30',
                'sunset' => '18:30',
            ]);
        }
    }
}
