<?php

namespace App\Services;

use App\Support\Untrusted;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Unico punto di contatto con l'endpoint `competitions-search` di federgolf.it
 * (admin-ajax di WordPress).
 *
 * Prima la stessa chiamata — stessi header, stesso body, stessa gestione della
 * risposta — esisteva in tre copie: nel comando di import, nel controller admin
 * e in quello utente. Un cambio di formato lato federgolf andava sistemato in
 * tre posti, e nel 2026-08 uno dei tre e' rimasto indietro.
 *
 * Questa classe si ferma al CONFINE: fa la chiamata, distingue i modi di
 * fallire e restituisce le righe grezze validate come array. Il FILTRO (gara
 * passata? annullata? rinviata?) e la forma del risultato restano di chi
 * chiama, perche' i tre casi d'uso li vogliono diversi davvero — l'admin
 * mostra anche le gare passate, l'utente no.
 *
 * NON normalizza gli identificativi. `competition_id` e' un token opaco emesso
 * da federgolf: numerico fino al 27/08/2026, GUID da allora (docs/STORICO.md).
 * Ogni tentativo di validarne o convertirne la forma ha gia' rotto la
 * produzione una volta.
 */
final class FedergolfCompetitionsClient
{
    /** Nessuna risposta entro il timeout, o connessione fallita. */
    public const REASON_TIMEOUT = 'timeout';

    /** HTTP 429: troppe richieste. */
    public const REASON_RATE_LIMIT = 'rate_limit';

    /** Qualunque altro HTTP non riuscito (4xx generico, 5xx). */
    public const REASON_HTTP = 'http';

    /** Risposta arrivata ma non e' JSON con un ramo `data` utilizzabile. */
    public const REASON_INVALID_FORMAT = 'invalid_format';

    /**
     * Scarica le gare di un anno solare.
     *
     * @return array{ok: true, rows: list<array<array-key, mixed>>, status: int}
     *         |array{ok: false, rows: list<array<array-key, mixed>>, reason: string, status: int, error: string}
     */
    public function fetchYear(int $anno): array
    {
        try {
            $response = Http::timeout(30)
                ->asForm()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post(Config::string('golf.fig.ajax_url'), [
                    'action' => 'competitions-search',
                    'tipo' => '',
                    'keyword' => '',
                    'anno' => (string) $anno,
                    'mese' => '',
                ]);
        } catch (ConnectionException $e) {
            return self::failure(self::REASON_TIMEOUT, 0, $e->getMessage());
        }

        $status = $response->status();

        if (! $response->successful()) {
            return self::failure(
                $status === 429 ? self::REASON_RATE_LIMIT : self::REASON_HTTP,
                $status
            );
        }

        $data = $response->json();

        // Corpo non-JSON (manutenzione, WAF) o senza il ramo `data`: e' un
        // errore esplicito, non una lista vuota spacciata per "nessun risultato".
        if (! is_array($data) || ! is_array($data['data'] ?? null)) {
            return self::failure(self::REASON_INVALID_FORMAT, $status);
        }

        return [
            'ok' => true,
            'rows' => Untrusted::rows($data['data']),
            'status' => $status,
        ];
    }

    /**
     * @return array{ok: false, rows: list<array<array-key, mixed>>, reason: string, status: int, error: string}
     */
    private static function failure(string $reason, int $status, string $error = ''): array
    {
        return [
            'ok' => false,
            'rows' => [],
            'reason' => $reason,
            'status' => $status,
            'error' => $error,
        ];
    }
}
