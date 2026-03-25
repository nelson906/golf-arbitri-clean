<?php

namespace App\Helpers;

use App\Enums\RefereeLevel;
use Illuminate\Support\Facades\Log;

/**
 * RefereeLevelsHelper
 *
 * Thin adapter sopra l'enum RefereeLevel.
 * Aggiunge solo ciò che l'enum non gestisce: normalizzazione di varianti
 * esterne (alias, abbreviazioni, stringhe non-standard) tramite VARIANTS_MAP.
 *
 * DB_ENUM_VALUES è mantenuto per retrocompatibilità ma delega a RefereeLevel.
 */
class RefereeLevelsHelper
{
    /**
     * Costante mantenuta per accesso statico legacy (es. array_keys(DB_ENUM_VALUES)).
     * Delegare a RefereeLevel::selectOptions(true) nelle nuove scritture.
     */
    public const DB_ENUM_VALUES = [
        'Aspirante'      => 'Aspirante',
        '1_livello'      => 'Primo Livello',
        'Regionale'      => 'Regionale',
        'Nazionale'      => 'Nazionale',
        'Internazionale' => 'Internazionale',
        'Archivio'       => 'Archivio',
    ];

    /**
     * Mapping da tutte le varianti (lowercase) ai valori ENUM database
     */
    public const VARIANTS_MAP = [
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
     * Ottieni tutti i livelli per select.
     * Delega a RefereeLevel::selectOptions() — fonte di verità.
     */
    public static function getSelectOptions(bool $includeArchived = false): array
    {
        return RefereeLevel::selectOptions($includeArchived);
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
        \Illuminate\Support\Facades\Log::warning('RefereeLevelsHelper: Unknown level variant', [
            'input_level' => $level,
            'lowercase' => $levelLower,
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
     * Verifica accesso tornei nazionali.
     * Delega a RefereeLevel::canAccessNational() — fonte di verità.
     */
    public static function canAccessNationalTournaments(?string $level): bool
    {
        return RefereeLevel::canAccessNational(self::normalize($level));
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

// Le funzioni globali referee_levels(), normalize_referee_level() e referee_level_label()
// si trovano in app/Helpers/helpers.php (caricato via Composer "files" autoload).
// NON ridefinirle qui: questo file ha namespace App\Helpers, quindi qualsiasi funzione
// definita qui sarebbe \App\Helpers\referee_levels(), non \referee_levels().
