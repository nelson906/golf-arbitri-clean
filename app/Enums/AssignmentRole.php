<?php

namespace App\Enums;

/**
 * Ruoli di un arbitro all'interno di un torneo.
 */
enum AssignmentRole: string
{
    case Referee            = 'Arbitro';
    case TournamentDirector = 'Direttore di Torneo';
    case Observer           = 'Osservatore';

    /**
     * Etichetta leggibile (coincide con il valore per questo dominio).
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Tutti i ruoli come array di valori stringa.
     * Utile per le regole di validazione:
     *   'role' => ['required', Rule::in(AssignmentRole::values())]
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
