<?php
// app/Http/Helpers/PermissionHelper.php

namespace App\Http\Helpers;

use Illuminate\Support\Facades\Auth;
use App\Models\Role;

class PermissionHelper
{
    /**
     * Email backdoor per lo sviluppatore con super poteri
     */
    const DEVELOPER_BACKDOOR_EMAIL = 'superadmin@grippa.it';

    /**
     * Verifica se l'utente corrente ha la backdoor dello sviluppatore
     */
    public static function hasDeveloperBackdoor(): bool
    {
        $user = Auth::user();
        return $user && $user->email === self::DEVELOPER_BACKDOOR_EMAIL;
    }

    /**
     * Verifica se l'utente ha accesso completo al sistema
     */
    public static function hasFullSystemAccess(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Backdoor per lo sviluppatore
        if (self::hasDeveloperBackdoor()) {
            return true;
        }

        // SuperAdmin ha accesso completo
        return $user->hasRole('SuperAdmin');
    }

    /**
     * Verifica se l'utente può gestire admin (solo SuperAdmin + backdoor sviluppatore)
     */
    public static function canManageAdmins(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Backdoor per lo sviluppatore
        if (self::hasDeveloperBackdoor()) {
            return true;
        }

        // Solo SuperAdmin possono gestire admin
        return $user->hasRole('SuperAdmin');
    }

    /**
     * Verifica se l'utente può gestire arbitri (Admin, SuperAdmin + backdoor)
     */
    public static function canManageReferees(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Backdoor per lo sviluppatore
        if (self::hasDeveloperBackdoor()) {
            return true;
        }

        // Admin e SuperAdmin possono gestire arbitri
        return $user->hasRole('Admin') || $user->hasRole('SuperAdmin') || $user->hasRole('NationalAdmin');
    }

    /**
     * Verifica se l'utente può eliminare arbitri (solo SuperAdmin + backdoor)
     */
    public static function canDeleteReferees(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Backdoor per lo sviluppatore
        if (self::hasDeveloperBackdoor()) {
            return true;
        }

        // Solo SuperAdmin e Admin possono eliminare arbitri
        return $user->hasRole('SuperAdmin') || $user->hasRole('Admin');
    }

    /**
     * Verifica se l'utente può creare/modificare admin (solo SuperAdmin + backdoor)
     */
    public static function canCreateAdmins(): bool
    {
        return self::canManageAdmins();
    }

    /**
     * Verifica se l'utente può assegnare ruoli admin/superadmin
     */
    public static function canAssignAdminRoles(): bool
    {
        return self::canManageAdmins();
    }

    /**
     * Ottiene i ruoli che l'utente corrente può assegnare
     */
    public static function getAssignableRoles(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        $roles = ['Referee']; // Tutti possono assegnare il ruolo base

        // Admin può assegnare solo Referee
        if ($user->hasRole('Admin')) {
            return $roles;
        }

        // SuperAdmin può assegnare Referee, Admin, NationalAdmin
        if ($user->hasRole('SuperAdmin') || self::hasDeveloperBackdoor()) {
            $roles = array_merge($roles, ['Admin', 'NationalAdmin']);
        }

        // NationalAdmin può assegnare solo Referee
        if ($user->hasRole('NationalAdmin')) {
            return ['Referee'];
        }

        return $roles;
    }

    /**
     * Verifica se l'utente può modificare un altro utente specifico
     */
    public static function canEditUser($targetUser): bool
    {
        $currentUser = Auth::user();

        if (!$currentUser || !$targetUser) {
            return false;
        }

        // Backdoor per lo sviluppatore può modificare chiunque
        if (self::hasDeveloperBackdoor()) {
            return true;
        }

        // Un utente può sempre modificare se stesso
        if ($currentUser->id === $targetUser->id) {
            return true;
        }

        // SuperAdmin può modificare chiunque
        if ($currentUser->hasRole('SuperAdmin')) {
            return true;
        }

        // Admin può modificare arbitri nella sua zona
        if ($currentUser->hasRole('Admin')) {
            if ($currentUser->referee && $targetUser->referee) {
                return $currentUser->referee->zone_id === $targetUser->referee->zone_id;
            }
        }

        return false;
    }

    /**
     * Ottiene la descrizione dei permessi dell'utente corrente
     */
    public static function getPermissionDescription(): string
    {
        $user = Auth::user();

        if (!$user) {
            return 'Utente non autenticato';
        }

        if (self::hasDeveloperBackdoor()) {
            return 'Accesso sviluppatore - Permessi completi';
        }

        if ($user->hasRole('SuperAdmin')) {
            return 'SuperAdmin - Accesso completo al sistema';
        }

        if ($user->hasRole('Admin')) {
            $zoneName = $user->referee?->zone?->name ?? 'N/A';
            return "Admin - Gestione zona: {$zoneName}";
        }

        if ($user->hasRole('NationalAdmin')) {
            return 'NationalAdmin - Gestione tornei nazionali';
        }

        if ($user->hasRole('Referee')) {
            $zoneName = $user->referee?->zone?->name ?? 'N/A';
            return "Arbitro - Zona: {$zoneName}";
        }

        return 'Utente base - Accesso limitato';
    }

    /**
     * Verifica se l'utente può gestire tornei
     */
    public static function canManageTournaments(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        if (self::hasDeveloperBackdoor()) {
            return true;
        }

        return $user->hasRole(['Admin', 'SuperAdmin', 'NationalAdmin']);
    }

    /**
     * Verifica se l'utente può gestire circoli
     */
    public static function canManageClubs(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        if (self::hasDeveloperBackdoor()) {
            return true;
        }

        return $user->hasRole(['Admin', 'SuperAdmin', 'NationalAdmin']);
    }

    /**
     * Verifica se l'utente può gestire zone (solo SuperAdmin)
     */
    public static function canManageZones(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        if (self::hasDeveloperBackdoor()) {
            return true;
        }

        return $user->hasRole('SuperAdmin');
    }

    /**
     * Verifica se l'utente può vedere tutti gli arbitri o solo quelli della sua zona
     */
    public static function canViewAllReferees(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        if (self::hasDeveloperBackdoor()) {
            return true;
        }

        return $user->hasRole(['SuperAdmin', 'NationalAdmin']);
    }
}
