<?php

namespace App\Enums;

/**
 * Stato del ciclo di vita di un torneo.
 *
 * Flusso standard:
 *   Draft → Open → Closed → Assigned → Completed
 *   (qualsiasi stato) → Cancelled
 */
enum TournamentStatus: string
{
    case Draft     = 'draft';
    case Open      = 'open';
    case Closed    = 'closed';
    case Assigned  = 'assigned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Il torneo può ancora essere modificato.
     * Un torneo completato o annullato è immutabile.
     */
    public function isEditable(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled]);
    }

    /**
     * Il torneo è in uno stato "attivo" (visibile nelle viste operative).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::Closed, self::Assigned]);
    }

    /**
     * Etichetta leggibile per l'interfaccia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Bozza',
            self::Open      => 'Aperto',
            self::Closed    => 'Chiuso',
            self::Assigned  => 'Assegnato',
            self::Completed => 'Completato',
            self::Cancelled => 'Annullato',
        };
    }

    /**
     * Colore CSS/Tailwind associato allo stato (per badge nelle view).
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Draft     => 'bg-gray-100 text-gray-700',
            self::Open      => 'bg-green-100 text-green-700',
            self::Closed    => 'bg-yellow-100 text-yellow-700',
            self::Assigned  => 'bg-blue-100 text-blue-700',
            self::Completed => 'bg-purple-100 text-purple-700',
            self::Cancelled => 'bg-red-100 text-red-700',
        };
    }

    /**
     * Tutti gli stati "attivi" come array di valori stringa.
     * Utile per query Eloquent: Tournament::whereIn('status', TournamentStatus::activeValues())
     *
     * @return string[]
     */
    public static function activeValues(): array
    {
        return array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->isActive())
        );
    }
}
