<?php

namespace App\Helpers;

use App\Models\Tournament;

/**
 * Helper centralizzato per la gestione delle zone
 */
class ZoneHelper
{
    /**
     * Ottiene il codice cartella per una zona specifica
     *
     * @param  int|null  $zoneId  ID della zona
     * @return string Codice cartella (es. 'SZR1', 'SZR2', ecc.)
     */
    public static function getFolderCode(?int $zoneId): string
    {
        if (! $zoneId) {
            return 'SZR0';
        }

        $mapping = config('golf.zones.folder_mapping', []);

        return $mapping[$zoneId] ?? 'SZR'.$zoneId;
    }

    /**
     * Ottiene il codice cartella per un torneo
     * Se il torneo è nazionale, restituisce il codice CRC
     *
     * @return string Codice cartella
     */
    public static function getFolderCodeForTournament(Tournament $tournament): string
    {
        // Se è nazionale, usa il codice CRC
        if (self::isTournamentNational($tournament)) {
            return config('golf.zones.national_folder_code', 'CRC');
        }

        // Altrimenti usa la zona del circolo
        $zoneId = $tournament->club->zone_id ?? $tournament->zone_id;

        return self::getFolderCode($zoneId);
    }

    /**
     * Verifica se un torneo è nazionale
     */
    public static function isTournamentNational(Tournament $tournament): bool
    {
        return $tournament->is_national
            || ($tournament->tournamentType && $tournament->tournamentType->is_national);
    }

    /**
     * Ottiene tutti i codici cartella disponibili
     */
    public static function getAllFolderCodes(): array
    {
        $mapping = config('golf.zones.folder_mapping', []);
        $nationalCode = config('golf.zones.national_folder_code', 'CRC');

        return array_merge(array_values($mapping), [$nationalCode]);
    }

    /**
     * Ottiene il nome della zona dato l'ID
     */
    public static function getZoneName(?int $zoneId): string
    {
        if (! $zoneId) {
            return 'Zona Non Specificata';
        }

        // Carica dal database se necessario
        $zone = \App\Models\Zone::find($zoneId);

        return $zone ? $zone->name : "Zona {$zoneId}";
    }

    /**
     * Ottiene l'email pattern per una zona
     */
    public static function getEmailPattern(int $zoneId): string
    {
        $pattern = config('golf.zones.default_email_pattern', 'szr{zone_id}@federgolf.it');

        return str_replace('{zone_id}', (string) $zoneId, $pattern);
    }

    /**
     * Verifica se un utente ha accesso a una zona specifica
     *
     * @param  \App\Models\User  $user
     */
    public static function userHasAccessToZone($user, int $zoneId): bool
    {
        // Super admin ha accesso a tutto
        if ($user->user_type === 'super_admin') {
            return true;
        }

        // National admin ha accesso a tutto
        if ($user->user_type === 'national_admin') {
            return true;
        }

        // Admin zonale ha accesso solo alla propria zona
        if ($user->user_type === 'admin') {
            return $user->zone_id === $zoneId;
        }

        // Referee ha accesso alla propria zona
        return $user->zone_id === $zoneId;
    }
}
