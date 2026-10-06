<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Config;

/**
 * Chi puo' usare il caricamento completo dei comitati da federgolf.it.
 *
 * Decisione 2026-10-05/06: un solo account, super admin, il cui indirizzo
 * sta SOLO nel .env del server (FIG_IMPORT_EMAIL), non nel codice pubblico.
 * Funzione temporanea: senza la riga nel .env la funzione non esiste
 * (niente voce di menu, pagina 404, nessun account nascosto).
 *
 * L'account e' nascosto agli altri utenti: non compare nell'elenco utenti
 * e la sua scheda risponde 404 a chiunque non sia lui stesso.
 */
final class FigImportAccess
{
    /** Indirizzo configurato, normalizzato; null se la funzione e' spenta. */
    public static function email(): ?string
    {
        $value = Config::get('golf.fig.import_email');

        if (! is_string($value)) {
            return null;
        }

        $email = strtolower(trim($value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    public static function isAccount(User $user): bool
    {
        $email = self::email();

        return $email !== null && strtolower(trim($user->email)) === $email;
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
