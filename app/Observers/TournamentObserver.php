<?php

namespace App\Observers;

use App\Models\Club;
use App\Models\Tournament;

/**
 * Observer per il modello Tournament.
 *
 * Mantiene sincronizzato il campo zone_id con il club associato,
 * eliminando la necessità dell'accessor getZoneIdAttribute() con
 * la sua query nascosta nel fallback.
 *
 * Registrazione in AppServiceProvider::boot():
 *   Tournament::observe(TournamentObserver::class);
 */
class TournamentObserver
{
    /**
     * Sincronizza zone_id prima di ogni salvataggio.
     *
     * Se club_id cambia, aggiorna automaticamente zone_id
     * prendendo il valore dal circolo associato.
     */
    public function saving(Tournament $tournament): void
    {
        if ($tournament->isDirty('club_id') && $tournament->club_id) {
            // Usa la relazione se già caricata, altrimenti query puntuale
            $zoneId = $tournament->relationLoaded('club')
                ? $tournament->club?->zone_id
                : Club::find($tournament->club_id)?->zone_id;

            // Usa setAttribute() per scrivere direttamente nell'array attributes
            $tournament->setAttribute('zone_id', $zoneId);
        }
    }

    /**
     * Dopo la creazione, assicura che zone_id sia sempre popolato.
     * Utile per record importati o creati via seeder senza passare da saving().
     */
    public function created(Tournament $tournament): void
    {
        if (! $tournament->getAttribute('zone_id') && $tournament->club_id) {
            $zoneId = Club::find($tournament->club_id)?->zone_id;

            if ($zoneId) {
                $tournament->updateQuietly(['zone_id' => $zoneId]);
            }
        }
    }
}
