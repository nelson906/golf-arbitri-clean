<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class RefereeRoleHelper
{
    /**
     * Ordine gerarchico dei ruoli
     */
    const ROLE_HIERARCHY = [
        'Direttore di Torneo' => 1,
        'Arbitro' => 2,
        'Osservatore' => 3
    ];

    /**
     * Get priority for sorting
     */
    public static function getRolePriority(string $role): int
    {
        return self::ROLE_HIERARCHY[$role] ?? 999;
    }

    /**
     * Sort assignments by role hierarchy
     */
    public static function sortByRole($assignments)
    {
        return $assignments->sort(function ($a, $b) {
            $priorityA = self::getRolePriority($a->role);
            $priorityB = self::getRolePriority($b->role);

            if ($priorityA === $priorityB) {
                // Se stesso ruolo, ordina per nome
                return strcmp($a->user->name, $b->user->name);
            }

            return $priorityA - $priorityB;
        });
    }
}
