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

    /**
     * Ruolo di default per le nuove assegnazioni.
     */
    public static function default(): self
    {
        return self::Referee;
    }

    /**
     * Priorità per l'ordinamento gerarchico (minore = prima).
     * Sostituisce RefereeRoleHelper::ROLE_HIERARCHY.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::TournamentDirector => 1,
            self::Referee            => 2,
            self::Observer           => 3,
        };
    }

    /**
     * Ordina una Collection di assegnazioni per gerarchia di ruolo, poi per nome.
     * Sostituisce RefereeRoleHelper::sortByRole().
     *
     * @param  \Illuminate\Support\Collection $assignments
     * @return \Illuminate\Support\Collection
     */
    public static function sortCollection(\Illuminate\Support\Collection $assignments): \Illuminate\Support\Collection
    {
        return $assignments->sort(function ($a, $b) {
            $orderA = self::tryFrom($a->role)?->sortOrder() ?? 999;
            $orderB = self::tryFrom($b->role)?->sortOrder() ?? 999;

            if ($orderA === $orderB) {
                return strcmp($a->user->name ?? '', $b->user->name ?? '');
            }

            return $orderA - $orderB;
        });
    }

    /**
     * Normalizza varianti testuali (inglese, abbreviazioni) al valore DB corretto.
     * Usato in DocumentGenerationService per importazioni esterne.
     */
    public static function normalize(string $role): self
    {
        return match (strtolower(trim($role))) {
            'tournament director', 'direttore di torneo' => self::TournamentDirector,
            'observer', 'osservatore'                    => self::Observer,
            default                                      => self::Referee,
        };
    }
}
