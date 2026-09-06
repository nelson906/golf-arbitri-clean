<?php

namespace App\Support;

/**
 * Lettura di dati NON fidati: risposte HTTP, JSON decodificato, HTML scrapato,
 * output di comandi di shell.
 *
 * Il punto di questa classe e' che il tipo di quei dati non e' deducibile: la
 * federgolf.it puo' cambiare la forma della sua risposta AJAX domani mattina
 * senza avvisare. Dichiarare una `array{...}` su quei valori sarebbe una
 * promessa non verificata che il type system crederebbe sulla parola, ed e'
 * esattamente il caso in cui un errore torna a runtime dopo essere sparito
 * dall'analisi statica.
 *
 * Qui invece si VALIDA: ogni lettura ha un fallback esplicito, quindi il tipo
 * dichiarato e' vero per costruzione.
 */
final class Untrusted
{
    /**
     * Valore come stringa. Numeri e booleani vengono resi in stringa
     * (com'e' tradizione di PHP sui dati di form); array, oggetti e null
     * danno il default.
     */
    public static function string(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return $default;
    }

    /**
     * Valore come intero. Le stringhe numeriche vengono convertite ("12" -> 12),
     * tutto il resto da' il default: "abc" non diventa 0 per caso.
     */
    public static function int(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Valore come array. Qualunque altra cosa (null, stringa, oggetto) da' [].
     *
     * @return array<array-key, mixed>
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Valore come lista di righe: tiene solo gli elementi che sono davvero
     * array. Serve per le collezioni di record che arrivano da JSON esterno,
     * dove una riga malformata non deve far saltare tutto il ciclo.
     *
     * @return list<array<array-key, mixed>>
     */
    public static function rows(mixed $value): array
    {
        $rows = [];

        foreach (self::array($value) as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Valore scalare LASCIATO COM'E': int, float, string e bool mantengono il
     * proprio tipo, tutto il resto (array, oggetti, null) diventa null.
     *
     * Serve per gli identificativi di terzi, che non vanno normalizzati: il
     * `competition_id` di federgolf e' un token opaco, numerico fino al
     * 27/08/2026 e GUID da allora (vedi docs/STORICO.md). Convertirlo — anche
     * solo in stringa — cambia il contratto verso il frontend e ha gia' rotto
     * la produzione una volta.
     */
    public static function scalarOrNull(mixed $value): int|float|string|bool|null
    {
        return is_scalar($value) ? $value : null;
    }

    /**
     * Come string(), ma un valore assente resta null invece di diventare ''.
     * Da usare dove il null e' parte del contratto (campi opzionali di una
     * risposta JSON che il frontend distingue da stringa vuota).
     */
    public static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = self::string($value, '');

        return $string === '' && ! is_string($value) ? null : $string;
    }

    /**
     * Lista di interi da una struttura non fidata: gli elementi non numerici
     * vengono scartati, non convertiti a 0.
     *
     * Serve per le liste di ID che arrivano da colonne JSON o da form e che
     * finiscono in un whereIn(): un 0 spurio li' e' silenzioso e sbagliato.
     *
     * @return list<int>
     */
    public static function intList(mixed $value): array
    {
        $ints = [];

        foreach (self::array($value) as $item) {
            if (is_int($item) || (is_string($item) && is_numeric($item)) || is_float($item)) {
                $ints[] = (int) $item;
            }
        }

        return $ints;
    }

    /**
     * Naviga una struttura annidata non fidata e restituisce il valore grezzo
     * all'arrivo, oppure null se un livello qualsiasi manca o non e' un array.
     */
    public static function at(mixed $value, string ...$path): mixed
    {
        $current = $value;

        foreach ($path as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }
}
