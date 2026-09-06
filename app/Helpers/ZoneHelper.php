<?php

namespace App\Helpers;

use App\Models\Tournament;
use Illuminate\Support\Facades\Config;

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

        $mapping = Config::array('golf.zones.folder_mapping', []);
        $code = $mapping[$zoneId] ?? null;

        return is_string($code) ? $code : 'SZR'.$zoneId;
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
            return Config::string('golf.zones.national_folder_code', 'CRC');
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
        // NB: la colonna is_national sta su tournament_types, non su tournaments.
        return (bool) ($tournament->tournamentType->is_national ?? false);
    }

    /**
     * Ottiene tutti i codici cartella disponibili
     *
     * @return list<string>
     */
    public static function getAllFolderCodes(): array
    {
        $mapping = Config::array('golf.zones.folder_mapping', []);
        $nationalCode = Config::string('golf.zones.national_folder_code', 'CRC');

        $codes = [];
        foreach ($mapping as $code) {
            if (is_string($code)) {
                $codes[] = $code;
            }
        }
        $codes[] = $nationalCode;

        return $codes;
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
        $pattern = Config::string('golf.zones.default_email_pattern', 'szr{zone_id}@federgolf.it');

        return str_replace('{zone_id}', (string) $zoneId, $pattern);
    }

    /**
     * Verifica se un utente ha accesso a una zona specifica
     *
     * @param  \App\Models\User  $user
     */
    public static function userHasAccessToZone($user, int $zoneId): bool
    {
        // Super admin e national admin hanno accesso a tutto
        if ($user->isNationalAdmin()) {
            return true;
        }

        // Admin zonale e referee hanno accesso alla propria zona
        return $user->zone_id === $zoneId;
    }
}
