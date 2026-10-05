<?php

namespace App\Support;

use App\Models\User;

/**
 * Chi puo' usare il caricamento completo dei comitati da federgolf.it.
 *
 * Decisione 2026-10-05: un solo account, super admin, con l'indirizzo
 * scritto qui. Nessun altro utente vede il pulsante o apre la pagina.
 * L'account e' nascosto agli altri utenti: non compare nell'elenco utenti
 * e la sua scheda risponde 404 a chiunque non sia lui stesso.
 */
final class FigImportAccess
{
    public const EMAIL = 'am.nelson906@gmail.com';

    public static function isAccount(User $user): bool
    {
        return strtolower(trim($user->email)) === self::EMAIL;
    }

    /** Vero se $target e' l'account riservato e chi guarda non e' lui. */
    public static function hiddenFrom(User $target, User $viewer): bool
    {
        return self::isAccount($target) && $target->id !== $viewer->id;
    }

    public static function allows(?User $user): bool
    {
        return $user !== null
            && $user->isSuperAdmin()
            && self::isAccount($user);
    }
}
