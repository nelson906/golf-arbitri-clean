<?php

namespace App\Policies;

use App\Models\Letterhead;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LetterheadPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any letterheads.
     */
    public function viewAny(User $user): bool
    {
        // Tutti gli admin e gli arbitri possono vedere le letterheads
        return in_array($user->user_type, ['super_admin', 'national_admin', 'admin', 'referee']);
    }

    /**
     * Determine whether the user can view the letterhead.
     */
    public function view(User $user, Letterhead $letterhead): bool
    {
        // Super admin può vedere tutto
        if ($user->user_type === 'super_admin') {
            return true;
        }

        // National admin può vedere tutto
        if ($user->user_type === 'national_admin') {
            return true;
        }

        // Zone admin può vedere solo le sue zone e quelle globali
        if ($user->user_type === 'admin' && $user->zone_id) {
            return $letterhead->zone_id === $user->zone_id || $letterhead->zone_id === null;
        }

        // Arbitri possono vedere solo le letterheads della loro zona e quelle globali
        if ($user->user_type === 'referee' && $user->zone_id) {
            return $letterhead->zone_id === $user->zone_id || $letterhead->zone_id === null;
        }

        return false;
    }

    /**
     * Determine whether the user can create letterheads.
     */
    public function create(User $user): bool
    {
        return in_array($user->user_type, ['super_admin', 'national_admin', 'admin']);
    }

    /**
     * Determine whether the user can update the letterhead.
     */
    public function update(User $user, Letterhead $letterhead): bool
    {
        // Super admin può modificare tutto
        if ($user->user_type === 'super_admin') {
            return true;
        }

        // National admin può modificare tutto
        if ($user->user_type === 'national_admin') {
            return true;
        }

        // Zone admin può modificare solo le sue zone
        if ($user->user_type === 'admin' && $user->zone_id) {
            return $letterhead->zone_id === $user->zone_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the letterhead.
     */
    public function delete(User $user, Letterhead $letterhead): bool
    {
        // Non può eliminare letterheads predefinite
        if ($letterhead->is_default) {
            return false;
        }

        // Super admin può eliminare tutto (tranne le default)
        if ($user->user_type === 'super_admin') {
            return true;
        }

        // National admin può eliminare tutto (tranne le default)
        if ($user->user_type === 'national_admin') {
            return true;
        }

        // Zone admin può eliminare solo le sue zone
        if ($user->user_type === 'admin' && $user->zone_id) {
            return $letterhead->zone_id === $user->zone_id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the letterhead.
     */
    public function restore(User $user, Letterhead $letterhead): bool
    {
        return $this->update($user, $letterhead);
    }

    /**
     * Determine whether the user can permanently delete the letterhead.
     */
    public function forceDelete(User $user, Letterhead $letterhead): bool
    {
        return $user->user_type === 'super_admin';
    }
}
