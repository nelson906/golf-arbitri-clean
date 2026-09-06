<?php

namespace App\Http\Concerns;

use App\Models\User;

/**
 * Risolve l'utente autenticato come tipo NON nullable.
 *
 * Tutte le route dell'applicazione stanno dietro il middleware `auth`
 * (vedi routes/web.php), quindi `auth()->user()` non e' mai null dentro
 * un'action. Invece di dichiararlo con un `@var` — che sarebbe una promessa
 * non verificata al type system — lo si IMPONE: se un domani un'action
 * finisse fuori dal gruppo `auth`, si ottiene un 401 pulito e non un
 * "Attempt to read property on null" in produzione.
 */
trait InteractsWithAuthUser
{
    protected function authUser(): User
    {
        $user = auth()->user();

        abort_if(! $user instanceof User, 401);

        return $user;
    }
}
