<?php

namespace App\Support;

use App\Enums\UserType;
use App\Models\User;

/**
 * Chi gestisce quali account (decisioni di Alberto, 7 ottobre 2026).
 *
 * - Gli arbitri, e gli admin di zona che sono arbitri della zona, li
 *   gestisce sempre la loro zona.
 * - Il CRC vede gli arbitri e li designa, ma non ne modifica le schede:
 *   gestisce solo gli account degli admin nazionali.
 * - Il super admin puo' tutto; solo lui sposta un utente da una zona
 *   all'altra e gestisce gli account super admin.
 * - Un super admin non puo' essere disattivato.
 */
final class UserManagement
{
    /** Puo' modificare, attivare/disattivare o eliminare l'account? */
    public static function canManage(User $actor, User $target): bool
    {
        if ($actor->user_type === UserType::SuperAdmin) {
            return true;
        }

        if ($target->user_type === UserType::SuperAdmin) {
            return false;
        }

        if ($actor->user_type === UserType::NationalAdmin) {
            return $target->user_type === UserType::NationalAdmin;
        }

        if ($actor->user_type === UserType::ZoneAdmin) {
            return $actor->zone_id !== null
                && $target->zone_id === $actor->zone_id
                && in_array($target->user_type, [UserType::Referee, UserType::ZoneAdmin], true);
        }

        return false;
    }

    /** Puo' disattivare (o riattivare) l'account? */
    public static function canToggleActive(User $actor, User $target): bool
    {
        return $target->user_type !== UserType::SuperAdmin
            && $actor->id !== $target->id
            && self::canManage($actor, $target);
    }

    /** Solo il super admin sposta un utente da una zona all'altra. */
    public static function canChangeZone(User $actor): bool
    {
        return $actor->user_type === UserType::SuperAdmin;
    }

    /**
     * Tipi di account che chi opera puo' creare o assegnare.
     *
     * @return array<string, string>
     */
    public static function assignableTypes(User $actor): array
    {
        return match ($actor->user_type) {
            UserType::SuperAdmin => [
                UserType::Referee->value => 'Arbitro',
                UserType::ZoneAdmin->value => 'Admin Zona',
                UserType::NationalAdmin->value => 'Admin Nazionale',
                UserType::SuperAdmin->value => 'Super Admin',
            ],
            UserType::NationalAdmin => [
                UserType::NationalAdmin->value => 'Admin Nazionale',
            ],
            UserType::ZoneAdmin => [
                UserType::Referee->value => 'Arbitro',
                UserType::ZoneAdmin->value => 'Admin Zona',
            ],
            default => [],
        };
    }
}
