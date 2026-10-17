<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;

/**
 * ID dei modelli passati a una view (lista, collection o pagina), letti
 * pretendendo che siano davvero modelli con chiave intera.
 */
trait CollectsIds
{
    /**
     * Nell'ordine in cui arrivano (per i test sugli ordinamenti).
     *
     * @return list<int>
     */
    protected function idsInOrder(mixed $items): array
    {
        if ($items instanceof Paginator) {
            $items = $items->items();
        }
        $this->assertIsIterable($items, 'La view non ha ricevuto una lista.');

        $ids = [];
        foreach ($items as $item) {
            $this->assertInstanceOf(Model::class, $item);
            $key = $item->getKey();
            $this->assertIsInt($key);
            $ids[] = $key;
        }

        return $ids;
    }

    /**
     * In ordine crescente (per confronti che non dipendono dall'ordine).
     *
     * @return list<int>
     */
    protected function sortedIds(mixed $items): array
    {
        $ids = $this->idsInOrder($items);
        sort($ids);

        return $ids;
    }

    /**
     * Righe di una colonna JSON (es. lo storico di un anno), ognuna un array.
     *
     * @return list<array<array-key, mixed>>
     */
    protected function jsonRows(mixed $value): array
    {
        $this->assertIsArray($value, 'Attesa una lista di righe.');

        $rows = [];
        foreach ($value as $row) {
            $this->assertIsArray($row, 'Riga non valida.');
            $rows[] = $row;
        }

        return $rows;
    }
}
