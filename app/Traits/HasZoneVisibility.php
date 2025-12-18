<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait per centralizzare la logica di visibilità basata su zona e ruolo.
 *
 * Regole di visibilità:
 * - super_admin: vede tutto
 * - national_admin: vede solo tornei con TournamentType.is_national = true
 * - admin (zonale): vede solo entità della propria zona
 * - referee nazionale/internazionale: propria zona + tornei nazionali
 * - referee 1_livello/regionale: solo propria zona
 */
trait HasZoneVisibility
{
    /**
     * Verifica se l'utente è super_admin
     */
    protected function isSuperAdmin(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return $user->user_type === 'super_admin';
    }

    /**
     * Verifica se l'utente è admin nazionale o super_admin
     */
    protected function isNationalAdmin(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return in_array($user->user_type, ['national_admin', 'super_admin']);
    }

    /**
     * Verifica se l'utente è un admin (qualsiasi tipo)
     */
    protected function isAdmin(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return in_array($user->user_type, ['admin', 'national_admin', 'super_admin']);
    }

    /**
     * Verifica se l'utente è admin zonale (non nazionale)
     */
    protected function isZoneAdmin(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return $user->user_type === 'admin';
    }

    /**
     * Verifica se il referee può accedere ai tornei nazionali
     */
    protected function isNationalReferee(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return in_array($user->level ?? '', ['Nazionale', 'Internazionale']);
    }

    /**
     * Ottiene la zone_id dell'utente corrente (o null se vede tutto)
     */
    protected function getUserZoneId(?User $user = null): ?int
    {
        $user = $user ?? auth()->user();

        return $user->zone_id;
    }

    /**
     * Applica filtro visibilità su query Tournament.
     *
     * @param  Builder  $query  Query su Tournament
     * @param  User|null  $user  Utente (default: auth user)
     */
    protected function applyTournamentVisibility(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        // Super admin vede tutto
        if ($this->isSuperAdmin($user)) {
            return $query;
        }

        // National admin vede solo tornei nazionali
        if ($user->user_type === 'national_admin') {
            return $query->whereHas('tournamentType', function ($q) {
                $q->where('is_national', true);
            });
        }

        // Admin zonale vede solo la propria zona
        if ($user->user_type === 'admin') {
            return $query->where('zone_id', $user->zone_id);
        }

        // Referee: dipende dal livello
        if ($user->user_type === 'referee') {
            if ($this->isNationalReferee($user)) {
                // Nazionale/Internazionale: propria zona + tornei nazionali
                return $query->where(function ($q) use ($user) {
                    $q->where('zone_id', $user->zone_id)
                        ->orWhereHas('tournamentType', function ($sub) {
                            $sub->where('is_national', true);
                        });
                });
            } else {
                // 1_livello/Regionale: solo propria zona
                return $query->where('zone_id', $user->zone_id);
            }
        }

        // Fallback: filtra per zona se presente
        if ($user->zone_id) {
            return $query->where('zone_id', $user->zone_id);
        }

        return $query;
    }

    /**
     * Applica filtro visibilità su query con relazione al torneo.
     * Utile per Assignment, Availability, Notification, ecc.
     *
     * @param  Builder  $query  Query su entità con relazione tournament
     * @param  User|null  $user  Utente (default: auth user)
     * @param  string  $tournamentRelation  Nome della relazione (default: 'tournament')
     */
    protected function applyTournamentRelationVisibility(
        Builder $query,
        ?User $user = null,
        string $tournamentRelation = 'tournament'
    ): Builder {
        $user = $user ?? auth()->user();

        // Super admin vede tutto
        if ($this->isSuperAdmin($user)) {
            return $query;
        }

        // National admin vede solo entità di tornei nazionali
        if ($user->user_type === 'national_admin') {
            return $query->whereHas($tournamentRelation, function ($q) {
                $q->whereHas('tournamentType', function ($sub) {
                    $sub->where('is_national', true);
                });
            });
        }

        // Admin zonale vede solo la propria zona
        if ($user->user_type === 'admin') {
            return $query->whereHas($tournamentRelation, function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        // Referee con accesso nazionale
        if ($user->user_type === 'referee' && $this->isNationalReferee($user)) {
            return $query->whereHas($tournamentRelation, function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id)
                    ->orWhereHas('tournamentType', function ($sub) {
                        $sub->where('is_national', true);
                    });
            });
        }

        // Default: filtra per zona
        if ($user->zone_id) {
            return $query->whereHas($tournamentRelation, function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        return $query;
    }

    /**
     * Applica filtro visibilità su query User (arbitri).
     *
     * @param  Builder  $query  Query su User
     * @param  User|null  $user  Utente (default: auth user)
     */
    protected function applyUserVisibility(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        // Super admin vede tutto
        if ($this->isSuperAdmin($user)) {
            return $query;
        }

        // National admin vede solo arbitri nazionali/internazionali
        if ($user->user_type === 'national_admin') {
            return $query->whereIn('level', ['Nazionale', 'Internazionale']);
        }

        // Admin zonale vede solo arbitri della propria zona
        if ($user->user_type === 'admin' && $user->zone_id) {
            return $query->where('zone_id', $user->zone_id);
        }

        return $query;
    }

    /**
     * Applica filtro visibilità su query Club.
     *
     * @param  Builder  $query  Query su Club
     * @param  User|null  $user  Utente (default: auth user)
     */
    protected function applyClubVisibility(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        // Super admin e national admin vedono tutto
        if ($this->isNationalAdmin($user)) {
            return $query;
        }

        // Admin zonale vede solo circoli della propria zona
        if ($user->zone_id) {
            return $query->where('zone_id', $user->zone_id);
        }

        return $query;
    }

    /**
     * Verifica se l'utente può accedere a un torneo specifico.
     *
     * @param  \App\Models\Tournament  $tournament
     */
    protected function canAccessTournament($tournament, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        // Super admin può tutto
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // National admin: solo tornei nazionali
        if ($user->user_type === 'national_admin') {
            return $tournament->tournamentType?->is_national ?? false;
        }

        // Admin zonale: solo propria zona
        if ($user->user_type === 'admin') {
            return $tournament->zone_id === $user->zone_id;
        }

        // Referee nazionale: propria zona o torneo nazionale
        if ($user->user_type === 'referee' && $this->isNationalReferee($user)) {
            return $tournament->zone_id === $user->zone_id
                || ($tournament->tournamentType?->is_national ?? false);
        }

        // Referee zonale: solo propria zona
        return $tournament->zone_id === $user->zone_id;
    }

    /**
     * Restituisce informazioni di contesto per le viste.
     */
    protected function getVisibilityContext(?User $user = null): array
    {
        $user = $user ?? auth()->user();

        return [
            'isSuperAdmin' => $this->isSuperAdmin($user),
            'isNationalAdmin' => $this->isNationalAdmin($user),
            'isAdmin' => $this->isAdmin($user),
            'isZoneAdmin' => $this->isZoneAdmin($user),
            'isNationalReferee' => $this->isNationalReferee($user),
            'userZoneId' => $user->zone_id,
            'userType' => $user->user_type,
            'userLevel' => $user->level ?? null,
        ];
    }
}
