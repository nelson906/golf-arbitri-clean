<?php

namespace App\Support;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Classe responsabile della logica di visibilità dei tornei.
 *
 * È il SINGLE SOURCE OF TRUTH per le regole di visibilità.
 * Sia il Trait HasZoneVisibility che gli Scope Eloquent (Tournament::scopeVisible,
 * User::scopeVisible) delegano a questa classe, evitando duplicazione.
 *
 * Regole:
 * - SuperAdmin:    vede tutto
 * - NationalAdmin: solo tornei con TournamentType.is_national = true
 * - ZoneAdmin:     solo tornei della propria zona (via club.zone_id)
 * - Referee Naz.:  propria zona + tornei nazionali
 * - Referee Zon.:  solo propria zona
 * - Fallback:      filtra per zona se presente, altrimenti nessun risultato
 */
final class TournamentVisibility
{
    /**
     * Applica il filtro di visibilità su una query Tournament.
     *
     * @param  Builder  $query  Query su Tournament (o relazione)
     * @param  User|null  $user  Utente corrente (default: auth()->user())
     */
    public static function apply(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (! $user || ! $user->user_type) {
            return $query->whereRaw('1 = 0');
        }

        /** @var UserType $type */
        $type = $user->user_type;

        return match (true) {
            $type->seesEverything()
                => $query,

            $type === UserType::NationalAdmin
                => $query->whereHas('tournamentType', fn (Builder $q) => $q->where('is_national', true)),

            $type === UserType::ZoneAdmin
                => $query->whereHas('club', fn (Builder $q) => $q->where('zone_id', $user->zone_id)),

            $type === UserType::Referee
                => self::applyRefereeFilter($query, $user),

            // Fallback generico
            (bool) $user->zone_id
                => $query->whereHas('club', fn (Builder $q) => $q->where('zone_id', $user->zone_id)),

            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Applica il filtro di visibilità su una query con relazione al torneo.
     * Usato per Assignment, Availability, TournamentNotification, ecc.
     *
     * @param  Builder  $query  Query sull'entità con relazione al torneo
     * @param  User|null  $user  Utente corrente
     * @param  string  $tournamentRelation  Nome della relazione Eloquent (default: 'tournament')
     */
    public static function applyViaRelation(
        Builder $query,
        ?User $user = null,
        string $tournamentRelation = 'tournament'
    ): Builder {
        $user = $user ?? auth()->user();

        if (! $user || ! $user->user_type) {
            return $query->whereRaw('1 = 0');
        }

        /** @var UserType $type */
        $type = $user->user_type;

        return match (true) {
            $type->seesEverything()
                => $query,

            $type === UserType::NationalAdmin
                => $query->whereHas($tournamentRelation, fn (Builder $q) => $q->whereHas(
                    'tournamentType',
                    fn (Builder $sub) => $sub->where('is_national', true)
                )),

            $type === UserType::ZoneAdmin
                => $query->whereHas($tournamentRelation, fn (Builder $q) => $q->whereHas(
                    'club',
                    fn (Builder $sub) => $sub->where('zone_id', $user->zone_id)
                )),

            $type === UserType::Referee
                => $query->whereHas(
                    $tournamentRelation,
                    fn (Builder $q) => self::applyRefereeFilter($q, $user)
                ),

            (bool) $user->zone_id
                => $query->whereHas($tournamentRelation, fn (Builder $q) => $q->whereHas(
                    'club',
                    fn (Builder $sub) => $sub->where('zone_id', $user->zone_id)
                )),

            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Verifica se un utente può accedere a un torneo specifico.
     *
     * @param  \App\Models\Tournament  $tournament
     */
    public static function canAccess($tournament, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (! $user || ! $user->user_type) {
            return false;
        }

        /** @var UserType $type */
        $type = $user->user_type;

        if ($type->seesEverything()) {
            return true;
        }

        $tournamentZoneId = $tournament->club?->zone_id ?? $tournament->attributes['zone_id'] ?? null;
        $isNationalTournament = $tournament->tournamentType?->is_national ?? false;

        return match ($type) {
            UserType::NationalAdmin => $isNationalTournament,
            UserType::ZoneAdmin     => $tournamentZoneId === $user->zone_id,
            UserType::Referee       => self::refereeCanAccess($tournament, $user, $tournamentZoneId, $isNationalTournament),
            default                 => $tournamentZoneId === $user->zone_id,
        };
    }

    // ── Metodi privati ────────────────────────────────────────────────────────

    private static function applyRefereeFilter(Builder $query, User $user): Builder
    {
        $isNational = $user->isNationalReferee();

        if ($isNational && $user->zone_id) {
            return $query->where(function (Builder $q) use ($user) {
                $q->whereHas('club', fn (Builder $sub) => $sub->where('zone_id', $user->zone_id))
                  ->orWhereHas('tournamentType', fn (Builder $sub) => $sub->where('is_national', true));
            });
        }

        if ($isNational) {
            // Nazionale senza zona: solo tornei nazionali
            return $query->whereHas('tournamentType', fn (Builder $q) => $q->where('is_national', true));
        }

        if ($user->zone_id) {
            // Zonale: solo propria zona
            return $query->whereHas('club', fn (Builder $q) => $q->where('zone_id', $user->zone_id));
        }

        // Referee senza zona e non nazionale: nessun risultato
        return $query->whereRaw('1 = 0');
    }

    private static function refereeCanAccess(
        $tournament,
        User $user,
        ?int $tournamentZoneId,
        bool $isNationalTournament
    ): bool {
        if ($user->isNationalReferee()) {
            return $tournamentZoneId === $user->zone_id || $isNationalTournament;
        }

        return $tournamentZoneId === $user->zone_id;
    }
}
