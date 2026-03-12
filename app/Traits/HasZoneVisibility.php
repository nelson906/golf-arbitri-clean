<?php

namespace App\Traits;

use App\Enums\RefereeLevel;
use App\Enums\UserType;
use App\Models\User;
use App\Support\TournamentVisibility;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait per centralizzare la logica di visibilità basata su zona e ruolo.
 *
 * I metodi apply* delegano a TournamentVisibility che è il SINGLE SOURCE OF TRUTH.
 * Questo Trait è solo un "bridge" conveniente per i Controller che lo usano.
 *
 * Regole di visibilità:
 * - super_admin:    vede tutto
 * - national_admin: vede solo tornei con TournamentType.is_national = true
 * - admin (zonale): vede solo entità della propria zona
 * - referee nazionale/internazionale: propria zona + tornei nazionali
 * - referee 1_livello/regionale: solo propria zona
 */
trait HasZoneVisibility
{
    // ── Check ruolo — delegano al Model User per evitare duplicazione ─────────

    protected function isSuperAdmin(?User $user = null): bool
    {
        return ($user ?? auth()->user())?->isSuperAdmin() ?? false;
    }

    protected function isNationalAdmin(?User $user = null): bool
    {
        return ($user ?? auth()->user())?->isNationalAdmin() ?? false;
    }

    protected function isAdmin(?User $user = null): bool
    {
        return ($user ?? auth()->user())?->isAdmin() ?? false;
    }

    protected function isZoneAdmin(?User $user = null): bool
    {
        return ($user ?? auth()->user())?->isZoneAdmin() ?? false;
    }

    protected function isNationalReferee(?User $user = null): bool
    {
        return ($user ?? auth()->user())?->isNationalReferee() ?? false;
    }

    protected function getUserZoneId(?User $user = null): ?int
    {
        return ($user ?? auth()->user())?->zone_id;
    }

    /**
     * Restituisce la zone_id appropriata per le query filtrate.
     * Restituisce null per admin che vedono tutto (super/national).
     */
    protected function getZoneIdForUser(?User $user = null): ?int
    {
        $user = $user ?? auth()->user();

        if (! $user || ! $user->user_type) {
            return null;
        }

        /** @var UserType $type */
        $type = $user->user_type;

        return $type->isNational() ? null : $user->zone_id;
    }

    // ── Visibilità query — delegano a TournamentVisibility ────────────────────

    /**
     * Applica filtro visibilità su query Tournament.
     * Delega a TournamentVisibility (single source of truth).
     */
    protected function applyTournamentVisibility(Builder $query, ?User $user = null): Builder
    {
        return TournamentVisibility::apply($query, $user);
    }

    /**
     * Applica filtro visibilità su query con relazione al torneo.
     * Utile per Assignment, Availability, Notification, ecc.
     *
     * @param  string  $tournamentRelation  Nome della relazione Eloquent (default: 'tournament')
     */
    protected function applyTournamentRelationVisibility(
        Builder $query,
        ?User $user = null,
        string $tournamentRelation = 'tournament'
    ): Builder {
        return TournamentVisibility::applyViaRelation($query, $user, $tournamentRelation);
    }

    /**
     * Applica filtro visibilità su query User (arbitri).
     *
     * Regole:
     * - super_admin:    vede tutto
     * - national_admin: solo arbitri nazionali/internazionali
     * - admin zonale:   solo arbitri della propria zona
     */
    protected function applyUserVisibility(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (! $user || ! $user->user_type) {
            return $query->whereRaw('1 = 0');
        }

        /** @var UserType $type */
        $type = $user->user_type;

        if ($type->seesEverything()) {
            return $query;
        }

        if ($type === UserType::NationalAdmin) {
            $nationalLevels = array_map(
                fn (RefereeLevel $l) => $l->value,
                array_filter(RefereeLevel::cases(), fn (RefereeLevel $l) => $l->isNational())
            );

            return $query->whereIn('level', $nationalLevels);
        }

        if ($type === UserType::ZoneAdmin && $user->zone_id) {
            return $query->where('zone_id', $user->zone_id);
        }

        return $query;
    }

    /**
     * Applica filtro visibilità su query Club.
     */
    protected function applyClubVisibility(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (! $user || ! $user->user_type) {
            return $query->whereRaw('1 = 0');
        }

        /** @var UserType $type */
        $type = $user->user_type;

        // Super admin e national admin vedono tutti i circoli
        if ($type->isNational()) {
            return $query;
        }

        if ($user->zone_id) {
            return $query->where('zone_id', $user->zone_id);
        }

        return $query;
    }

    /**
     * Verifica se l'utente può accedere a un torneo specifico.
     * Delega a TournamentVisibility (single source of truth).
     */
    protected function canAccessTournament($tournament, ?User $user = null): bool
    {
        return TournamentVisibility::canAccess($tournament, $user);
    }

    /**
     * Restituisce informazioni di contesto per le viste.
     */
    protected function getVisibilityContext(?User $user = null): array
    {
        $user = $user ?? auth()->user();

        return [
            'isSuperAdmin'     => $this->isSuperAdmin($user),
            'isNationalAdmin'  => $this->isNationalAdmin($user),
            'isAdmin'          => $this->isAdmin($user),
            'isZoneAdmin'      => $this->isZoneAdmin($user),
            'isNationalReferee' => $this->isNationalReferee($user),
            'userZoneId'       => $user?->zone_id,
            'userType'         => $user?->user_type?->value,
            'userLevel'        => $user?->level ?? null,
        ];
    }
}
