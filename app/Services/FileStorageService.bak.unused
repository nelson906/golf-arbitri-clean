<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileStorageService
{
    /**
     * Salva file nella zona corretta
     */
public function storeInZone($fileData, Tournament $tournament, $extension)
{
    Log::info('Starting storeInZone', [
        'tournament_id' => $tournament->id,
        'extension' => $extension,
        'file_data' => array_keys($fileData)
    ]);
    $zone = $this->getZoneFolder($tournament);
    $filename = $fileData['filename'];

    $relativePath = "convocazioni/{$zone}/generated/{$filename}";

    // PRIMA leggi il contenuto
    if (!file_exists($fileData['path'])) {
        Log::error('Source file does not exist', [
            'path' => $fileData['path'],
            'tournament_id' => $tournament->id
        ]);
        throw new \Exception("File sorgente non trovato: {$fileData['path']}");
    }
    
    $content = file_get_contents($fileData['path']);
    
    if (!$content) {
        Log::error('Could not read file content', [
            'path' => $fileData['path'],
            'tournament_id' => $tournament->id
        ]);
        throw new \Exception("Impossibile leggere il contenuto del file: {$fileData['path']}");
    }

    // Assicurati che la directory esista
    $fullPath = Storage::disk('public')->path(dirname($relativePath));
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0777, true);
    }

    // POI salva
    try {
        Log::info('Attempting to store file', [
            'full_path' => $fullPath,
            'relative_path' => $relativePath,
            'exists' => is_dir($fullPath)
        ]);
        $saved = Storage::disk('public')->put($relativePath, $content);
        if (!$saved) {
            Log::error('Failed to store file', [
                'relative_path' => $relativePath,
                'tournament_id' => $tournament->id
            ]);
            throw new \Exception("Impossibile salvare il file in: {$relativePath}");
        }
        Log::info('File stored successfully', [
            'relative_path' => $relativePath,
            'tournament_id' => $tournament->id
        ]);
    } catch (\Exception $e) {
        Log::error('Exception storing file', [
            'relative_path' => $relativePath,
            'tournament_id' => $tournament->id,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }

    // INFINE elimina il file temporaneo
    if (file_exists($fileData['path'])) {
        unlink($fileData['path']);
    }

    return $relativePath;
}

    /**
     * Ottieni path per lettera circolo
     */
    public function getClubLetterPath(Tournament $tournament): string
    {
        $zone = $this->getZoneFolder($tournament);
        $filename = Str::slug($tournament->club->name) . '-' . Str::slug($tournament->name) . '.docx';
        return "convocazioni/{$zone}/generated/{$filename}";
    }

    /**
     * Elimina convocazione
     */
    public function deleteConvocation(Tournament $tournament): bool
    {
        if ($tournament->convocation_file_path) {
            return Storage::disk('public')->delete($tournament->convocation_file_path);
        }
        return false;
    }

    /**
     * Elimina lettera circolo
     */
    public function deleteClubLetter(Tournament $tournament): bool
    {
        if ($tournament->club_letter_file_path) {
            return Storage::disk('public')->delete($tournament->club_letter_file_path);
        }
        return false;
    }

    /**
     * Recupera nome cartella zona corretta
     */
    public function getZoneFolder(Tournament $tournament): string
    {
        // Se è nazionale, va in CRC
        if ($tournament->is_national ||
            ($tournament->tournamentType && $tournament->tournamentType->is_national)) {
            return 'CRC';
        }

        // Altrimenti usa la zona del circolo
        $zoneId = $tournament->club->zone_id ?? $tournament->zone_id;

        return match($zoneId) {
            1 => 'SZR1',
            2 => 'SZR2',
            3 => 'SZR3',
            4 => 'SZR4',
            5 => 'SZR5',
            6 => 'SZR6',
            7 => 'SZR7',
            default => 'SZR' . $zoneId
        };
    }
}
