<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

/**
 * RefereeLevelsHelper - VERSIONE SEMPLIFICATA
 *
 * Usa strtolower() per gestire tutti i casi e varianti
 */
class RefereeLevelsHelper
{
    /**
     * Valori ENUM del database (chiavi = valori DB, valori = label utente)
     */
    const DB_ENUM_VALUES = [
        'Aspirante' => 'Aspirante',
        '1_livello' => 'Primo Livello',
        'Regionale' => 'Regionale',
        'Nazionale' => 'Nazionale',
        'Internazionale' => 'Internazionale',
        'Archivio' => 'Archivio',
    ];

    /**
     * Mapping da tutte le varianti (lowercase) ai valori ENUM database
     */
    const VARIANTS_MAP = [
        // Aspirante
        'aspirante' => 'Aspirante',
        'asp' => 'Aspirante',

        // Primo livello → 1_livello
        'primo_livello' => '1_livello',
        'primo-livello' => '1_livello',
        'primo livello' => '1_livello',
        '1_livello' => '1_livello',
        '1livello' => '1_livello',
        'first_level' => '1_livello',
        'prim' => '1_livello',

        // Regionale
        'regionale' => 'Regionale',
        'reg' => 'Regionale',

        // Nazionale
        'nazionale' => 'Nazionale',
        'naz' => 'Nazionale',

        // Internazionale
        'internazionale' => 'Internazionale',
        'int' => 'Internazionale',

        // Archivio
        'archivio' => 'Archivio',
    ];

    /**
     * Ottieni tutti i livelli per select
     */
    public static function getSelectOptions(bool $includeArchived = false): array
    {
        $levels = self::DB_ENUM_VALUES;

        if (!$includeArchived) {
            unset($levels['Archivio']);
        }

        return $levels;
    }

    /**
     * Normalizza qualsiasi variante al valore ENUM database
     */
    public static function normalize(?string $level): ?string
    {
        if (empty($level)) {
            return null;
        }

        // Se è già un valore ENUM valido, restituiscilo
        if (array_key_exists($level, self::DB_ENUM_VALUES)) {
            return $level;
        }

        // Converti in lowercase e cerca nel mapping
        $levelLower = strtolower(trim($level));

        if (array_key_exists($levelLower, self::VARIANTS_MAP)) {
            return self::VARIANTS_MAP[$levelLower];
        }

        // Se non trovato, log warning e restituisci originale
        \Log::warning("RefereeLevelsHelper: Unknown level variant", [
            'input_level' => $level,
            'lowercase' => $levelLower
        ]);

        return $level;
    }

    /**
     * Ottieni label user-friendly
     */
    public static function getLabel(?string $level): string
    {
        if (empty($level)) {
            return 'Non specificato';
        }

        $normalized = self::normalize($level);

        return self::DB_ENUM_VALUES[$normalized] ?? ucfirst($level);
    }

    /**
     * Verifica se un livello è valido
     */
    public static function isValid(?string $level): bool
    {
        if (empty($level)) {
            return false;
        }

        $normalized = self::normalize($level);
        return array_key_exists($normalized, self::DB_ENUM_VALUES);
    }

    /**
     * Verifica accesso tornei nazionali
     */
    public static function canAccessNationalTournaments(?string $level): bool
    {
        $normalized = self::normalize($level);
        return in_array($normalized, ['Nazionale', 'Internazionale']);
    }

    /**
     * Debug helper
     */
    public static function debugLevel(string $level): array
    {
        $normalized = self::normalize($level);
        $levelLower = strtolower(trim($level));

        return [
            'original' => $level,
            'lowercase' => $levelLower,
            'normalized' => $normalized,
            'label' => self::getLabel($level),
            'is_valid' => self::isValid($level),
            'can_access_national' => self::canAccessNationalTournaments($level),
            'found_in_enum' => array_key_exists($level, self::DB_ENUM_VALUES),
            'found_in_variants' => array_key_exists($levelLower, self::VARIANTS_MAP),
            'database_enum_values' => array_keys(self::DB_ENUM_VALUES),
        ];
    }

    /**
     * Ottieni tutte le varianti per debug
     */
    public static function getAllVariants(): array
    {
        return array_merge(
            array_keys(self::DB_ENUM_VALUES),
            array_keys(self::VARIANTS_MAP)
        );
    }
}

/**
 * Funzioni helper globali
 */
if (!function_exists('referee_levels')) {
    function referee_levels(bool $includeArchived = false): array
    {
        return RefereeLevelsHelper::getSelectOptions($includeArchived);
    }
}

if (!function_exists('normalize_referee_level')) {
    function normalize_referee_level(?string $level): ?string
    {
        return RefereeLevelsHelper::normalize($level);
    }
}

if (!function_exists('referee_level_label')) {
    function referee_level_label(?string $level): string
    {
        return RefereeLevelsHelper::getLabel($level);
    }
}
