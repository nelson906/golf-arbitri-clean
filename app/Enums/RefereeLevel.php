<?php

namespace App\Enums;

/**
 * Livelli di qualifica degli arbitri di golf.
 *
 * I livelli Nazionale e Internazionale conferiscono accesso
 * ai tornei nazionali oltre a quelli della propria zona.
 */
enum RefereeLevel: string
{
    case Aspirante      = 'Aspirante';
    case PrimoLivello   = '1_livello';
    case Regionale      = 'Regionale';
    case Nazionale      = 'Nazionale';
    case Internazionale = 'Internazionale';
    case Archivio       = 'Archivio';

    /**
     * L'arbitro ha accesso ai tornei nazionali.
     */
    public function isNational(): bool
    {
        return in_array($this, [
            self::Nazionale,
            self::Internazionale,
        ]);
    }

    /**
     * L'arbitro è ancora attivo (non archiviato).
     */
    public function isActive(): bool
    {
        return $this !== self::Archivio;
    }

    /**
     * Etichetta leggibile per l'interfaccia.
     */
    public function label(): string
    {
        return match ($this) {
            self::PrimoLivello => 'Primo Livello',
            default            => $this->value,
        };
    }

    /**
     * Tutti i livelli attivi (escluso Archivio), in ordine crescente.
     *
     * @return self[]
     */
    public static function activeLevels(): array
    {
        return [
            self::Aspirante,
            self::PrimoLivello,
            self::Regionale,
            self::Nazionale,
            self::Internazionale,
        ];
    }

    /**
     * Array associativo valore-DB => etichetta, usabile nei <select>.
     * Unica fonte di verità: sostituisce User::LEVELS e RefereeLevelsHelper::DB_ENUM_VALUES.
     *
     * @return array<string,string>
     */
    public static function selectOptions(bool $includeArchived = false): array
    {
        $cases = $includeArchived ? self::cases() : self::activeLevels();

        $result = [];
        foreach ($cases as $case) {
            $result[$case->value] = $case->label();
        }

        return $result;
    }

    /**
     * Verifica se un livello stringa ha accesso ai tornei nazionali.
     * Delega a isNational() sull'istanza; null-safe su input invalidi.
     */
    public static function canAccessNational(?string $value): bool
    {
        return self::tryFrom($value ?? '')?->isNational() ?? false;
    }
}
