<?php

namespace App\Policies;

use App\Models\Communication;
use App\Models\User;

class CommunicationPolicy
{
    /**
     * Determine whether the user can view any communications.
     */
    public function viewAny(User $user): bool
    {
        return true; // Tutti possono vedere la lista
    }

    /**
     * Determine whether the user can view the communication.
     */
    public function view(User $user, Communication $communication): bool
    {
        // Admin può vedere tutto (isAdmin() copre super_admin, national_admin, zone_admin)
        if ($user->isAdmin()) {
            return true;
        }

        // Altri utenti possono vedere comunicazioni globali o della propria zona
        return $communication->zone_id === null || $communication->zone_id === $user->zone_id;
    }

    /**
     * Determine whether the user can create communications.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the communication.
     */
    public function update(User $user, Communication $communication): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the communication.
     */
    public function delete(User $user, Communication $communication): bool
    {
        return $user->isAdmin();
    }
}
