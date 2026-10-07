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
     * Colori bordo vista admin: torneo da giocare / gia' giocato.
     * (Lo stato del torneo non esiste piu': decisione 2026-10-03.)
     */
    private const BORDER_UPCOMING = '#10B981'; // Green

    private const BORDER_PAST = '#374151';     // Dark Gray

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

    private const TEXT_DARK = '#111827';

    private const TEXT_LIGHT = '#FFFFFF';



    /**
     * Colore del testo leggibile sopra uno sfondo: scuro sui colori chiari
     * (giallo, azzurro...), bianco sugli scuri. Un colore #RRGGBBAA viene
     * considerato sopra il bianco del calendario.
     */
    public function textColorFor(string $background): string
    {
        $hex = ltrim(trim($background), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $hex)) {
            return self::TEXT_LIGHT;
        }

        $alpha = strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0;
        $luminance = 0.0;
        foreach ([0 => 0.2126, 2 => 0.7152, 4 => 0.0722] as $offset => $weight) {
            $channel = hexdec(substr($hex, $offset, 2)) / 255;
            $channel = $channel * $alpha + (1 - $alpha); // sopra il bianco
            $linear = $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            $luminance += $weight * $linear;
        }

        // Luminanza relativa (WCAG): sopra 0,4 il bianco non si legge piu'.
        // Soglia piu' alta del punto di parita' (0,18) per lasciare il bianco
        // sui colori medi (blu, verde) come prima.
        return $luminance > 0.4 ? self::TEXT_DARK : self::TEXT_LIGHT;
    }

    /**
     * Ottieni colore evento per vista ADMIN (basato su tipo torneo)
     */
    public function getAdminEventColor(Tournament $tournament): string
    {
        return $this->typeColor($tournament->tournamentType);
    }

    /**
     * Colore del tipo torneo: quello impostato in Tipi Torneo; in mancanza la
     * mappa storica per sigla, poi il colore predefinito.
     */
    private function typeColor(?TournamentType $type): string
    {
        if ($type === null) {
            return self::DEFAULT_COLOR;
        }

        $color = trim((string) $type->calendar_color);
        if ($color !== '') {
            return $color;
        }

        return self::TYPE_COLORS[$type->short_name] ?? self::DEFAULT_COLOR;
    }

    /**
     * Ottieni colore bordo per vista ADMIN (torneo da giocare o gia' giocato)
     */
    public function getAdminBorderColor(Tournament $tournament): string
    {
        $end = $tournament->end_date ?? $tournament->start_date;

        return $end < now()->startOfDay() ? self::BORDER_PAST : self::BORDER_UPCOMING;
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
     *
     * @return array<string, string>
     */
    public function getAdminLegendColors(): array
    {
        // Ottieni tutti i tipi di torneo attivi dal database
        $types = TournamentType::where('is_active', true)
            ->orderBy('name')
            ->get();

        $legend = [];
        foreach ($types as $type) {
            $legend[$type->name] = $this->typeColor($type);
        }

        return $legend;
    }

    /**
     * Ottieni array colori per legenda arbitro
     *
     * @return array<string, mixed>
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
