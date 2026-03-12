<?php

namespace App\Enums;

/**
 * Tipi di utente del sistema golf arbitri.
 *
 * - SuperAdmin:    accesso totale a tutte le zone e tornei
 * - NationalAdmin: vede solo tornei nazionali (is_national = true)
 * - ZoneAdmin:     vede solo entità della propria zona
 * - Referee:       visibilità dipende dal livello (v. RefereeLevel)
 */
enum UserType: string
{
    case SuperAdmin    = 'super_admin';
    case NationalAdmin = 'national_admin';
    case ZoneAdmin     = 'admin';
    case Referee       = 'referee';

    /**
     * L'utente è un qualsiasi tipo di admin (non referee).
     */
    public function isAdmin(): bool
    {
        return in_array($this, [
            self::SuperAdmin,
            self::NationalAdmin,
            self::ZoneAdmin,
        ]);
    }

    /**
     * L'utente ha visibilità nazionale o globale.
     */
    public function isNational(): bool
    {
        return in_array($this, [self::SuperAdmin, self::NationalAdmin]);
    }

    /**
     * L'utente vede tutto senza filtri di zona.
     */
    public function seesEverything(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * Etichetta leggibile per l'interfaccia.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin    => 'Super Amministratore',
            self::NationalAdmin => 'Amministratore Nazionale',
            self::ZoneAdmin     => 'Amministratore Zonale',
            self::Referee       => 'Arbitro',
        };
    }
}
