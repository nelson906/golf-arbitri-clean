<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentType;

/**
 * Servizio centralizzato per la gestione dei colori calendario tornei.
 *
 * Unifica la logica di colorazione usata in:
 * - Admin/TournamentController (vista admin)
 * - TournamentController (vista mista)
 * - User/AvailabilityController (vista arbitro)
 */
class TournamentColorService
{
    /**
     * Colori per tipo torneo (admin view) basati su short_name
     */
    private const TYPE_COLORS = [
        // 🟢 GARE GIOVANILI (Verde chiaro)
        'G12' => '#96CEB4',
        'G14' => '#96CEB4',
        'G16' => '#96CEB4',
        'G18' => '#96CEB4',
        'S14' => '#96CEB4',
        'T18' => '#96CEB4',
        'USK' => '#96CEB4',

        // 🔵 GARE NORMALI (Blu)
        'GN36' => '#45B7D1',
        'GN54' => '#45B7D1',
        'GN72' => '#45B7D1',
        'MP' => '#45B7D1',
        'EVEN' => '#45B7D1',

        // 🟡 TROFEI (Teal)
        'TG' => '#4ECDC4',
        'TGF' => '#4ECDC4',
        'TR' => '#4ECDC4',
        'TNZ' => '#4ECDC4',

        // 🔴 CAMPIONATI (Rosso)
        'CR' => '#FF6B6B',
        'CNZ' => '#FF6B6B',
        'CI' => '#FF6B6B',

        // 🟠 PROFESSIONALI (Amber)
        'PRO' => '#F59E0B',
        'PATR' => '#F59E0B',
        'GRS' => '#F59E0B',
    ];

    /**
     * Colori per stato torneo (border admin)
     */
    private const STATUS_COLORS = [
        'draft' => '#F59E0B',       // Amber
        'open' => '#10B981',        // Green
        'closed' => '#6B7280',      // Gray
        'assigned' => '#059669',    // Dark Green
        'completed' => '#374151',   // Dark Gray
        'cancelled' => '#EF4444',   // Red
    ];

    /**
     * Colori per stato personale arbitro
     */
    private const PERSONAL_COLORS = [
        'assigned' => '#10B981',    // Green
        'available' => '#F59E0B',   // Yellow/Orange
        'can_apply' => '#3B82F6',   // Blue
    ];

    /**
     * Colori border per stato personale arbitro
     */
    private const PERSONAL_BORDER_COLORS = [
        'assigned' => '#059669',    // Dark green
        'available' => '#D97706',   // Dark yellow
        'can_apply' => '#1E40AF',   // Dark blue
    ];

    private const DEFAULT_COLOR = '#3B82F6';

    private const DEFAULT_BORDER = '#10B981';

    // Aggiunto qui per visibilità dal metodo pubblico
    public const TYPE_COLORS_MAP = self::TYPE_COLORS;

    /**
     * Ottieni colore evento per vista ADMIN (basato su tipo torneo)
     */
    public function getAdminEventColor(Tournament $tournament): string
    {
        $shortName = $tournament->tournamentType->short_name ?? 'default';

        return self::TYPE_COLORS[$shortName] ?? self::DEFAULT_COLOR;
    }

    /**
     * Ottieni colore bordo per vista ADMIN (basato su stato torneo)
     */
    public function getAdminBorderColor(Tournament $tournament): string
    {
        return self::STATUS_COLORS[$tournament->status] ?? self::DEFAULT_BORDER;
    }

    /**
     * Ottieni colore evento per vista ARBITRO (basato su stato personale)
     */
    public function getRefereeEventColor(Tournament $tournament, bool $isAssigned, bool $isAvailable): string
    {
        if ($isAssigned) {
            return self::PERSONAL_COLORS['assigned'];
        }

        if ($isAvailable) {
            return self::PERSONAL_COLORS['available'];
        }

        // Usa colore del tournament type se disponibile
        if ($tournament->tournamentType?->calendar_color) {
            $color = $tournament->tournamentType->calendar_color;
            // Aggiungi trasparenza per tornei non assegnati/disponibili
            if (str_starts_with($color, '#')) {
                return $color.'80'; // 50% trasparenza
            }

            return $color;
        }

        return self::PERSONAL_COLORS['can_apply'];
    }

    /**
     * Ottieni colore bordo per vista ARBITRO (basato su stato personale)
     */
    public function getRefereeBorderColor(bool $isAssigned, bool $isAvailable): string
    {
        if ($isAssigned) {
            return self::PERSONAL_BORDER_COLORS['assigned'];
        }

        if ($isAvailable) {
            return self::PERSONAL_BORDER_COLORS['available'];
        }

        return self::PERSONAL_BORDER_COLORS['can_apply'];
    }

    /**
     * Ottieni colore evento generico (per viste miste admin/arbitro)
     */
    public function getEventColor(Tournament $tournament, bool $isAssigned = false, bool $isAvailable = false, bool $isAdmin = false): string
    {
        if ($isAdmin) {
            return $tournament->tournamentType?->calendar_color ?? self::DEFAULT_COLOR;
        }

        return $this->getRefereeEventColor($tournament, $isAssigned, $isAvailable);
    }

    /**
     * Ottieni colore bordo generico (per viste miste admin/arbitro)
     */
    public function getBorderColor(Tournament $tournament, bool $isAssigned = false, bool $isAvailable = false, bool $isAdmin = false): string
    {
        if ($isAdmin) {
            return $this->getAdminBorderColor($tournament);
        }

        return $this->getRefereeBorderColor($isAssigned, $isAvailable);
    }

    /**
     * Ottieni lo stato personale dell'arbitro
     */
    public function getPersonalStatus(bool $isAssigned, bool $isAvailable): string
    {
        if ($isAssigned) {
            return 'assigned';
        }
        if ($isAvailable) {
            return 'available';
        }

        return 'can_apply';
    }

    /**
     * Ottieni array colori per legenda admin (con nomi completi)
     */
    public function getAdminLegendColors(): array
    {
        // Ottieni tutti i tipi di torneo attivi dal database
        $types = TournamentType::where('is_active', true)
            ->orderBy('name')
            ->get();

        $legend = [];
        foreach ($types as $type) {
            $shortName = $type->short_name ?? $type->name;
            // Usa short_name come chiave per il mapping colore, ma nome completo come label
            $color = self::TYPE_COLORS[$shortName] ?? self::DEFAULT_COLOR;
            $legend[$type->name] = $color;
        }

        return $legend;
    }

    /**
     * Ottieni array colori per legenda arbitro
     */
    public function getRefereeLegendColors(): array
    {
        return [
            'Assegnato' => self::PERSONAL_COLORS['assigned'],
            'Disponibile' => self::PERSONAL_COLORS['available'],
            'Disponibile per candidatura' => self::PERSONAL_COLORS['can_apply'],
        ];
    }
}
