<?php

namespace Tests\Unit\Services;

use App\Models\Zone;
use App\Services\AvailabilityNotificationService;
use Tests\TestCase;

/**
 * Regression test per FIX A-1 e FIX A-2.
 *
 * FIX A-1: getZoneEmail() usava un array PHP hardcoded invece di leggere
 *           zones.email dal database. Email cambiate nel DB venivano ignorate.
 *
 * FIX A-2: getCrcEmail() restituiva 'crc@federgolf.it' hardcoded invece
 *           di leggere da config('golf.emails.crc').
 *
 * Eseguire con: php artisan test --filter=AvailabilityEmailResolutionTest
 */
class AvailabilityEmailResolutionTest extends TestCase
{
    /** Espone i metodi protected per i test tramite reflection. */
    private function callProtected(object $service, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($service, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($service, $args);
    }

    private function makeService(): AvailabilityNotificationService
    {
        return app(AvailabilityNotificationService::class);
    }

    // ───────────────────────────────────────────
    //  FIX A-1 — getZoneEmail() legge dal DB
    // ───────────────────────────────────────────

    /**
     * getZoneEmail() deve restituire l'email della zona dal database.
     */
    public function test_get_zone_email_reads_from_database(): void
    {
        // Aggiorna l'email della zona esistente (creata da seedZones in TestCase)
        $zone = Zone::first();
        $zone->update(['email' => 'zona-test-db@example.com']);

        $result = $this->callProtected($this->makeService(), 'getZoneEmail', [$zone->id]);

        $this->assertEquals('zona-test-db@example.com', $result);
    }

    /**
     * getZoneEmail() deve restituire il fallback se la zona non ha email.
     */
    public function test_get_zone_email_returns_fallback_when_zone_email_is_null(): void
    {
        $zone = Zone::first();
        $zone->update(['email' => null]);

        $result = $this->callProtected($this->makeService(), 'getZoneEmail', [$zone->id]);

        $expectedFallback = config('golf.emails.fallback_zone', 'arbitri@federgolf.it');
        $this->assertEquals($expectedFallback, $result);
    }

    /**
     * getZoneEmail() deve restituire il fallback se la zona non esiste.
     */
    public function test_get_zone_email_returns_fallback_for_nonexistent_zone(): void
    {
        $result = $this->callProtected($this->makeService(), 'getZoneEmail', [99999]);

        $expectedFallback = config('golf.emails.fallback_zone', 'arbitri@federgolf.it');
        $this->assertEquals($expectedFallback, $result);
    }

    /**
     * Verifica che email diverse per zone diverse vengano risolte correttamente.
     */
    public function test_get_zone_email_resolves_different_emails_per_zone(): void
    {
        $zones = Zone::take(3)->get();

        $emails = [
            'szr1-custom@example.com',
            'szr2-custom@example.com',
            'szr3-custom@example.com',
        ];

        foreach ($zones->values() as $index => $zone) {
            $zone->update(['email' => $emails[$index]]);
        }

        $service = $this->makeService();

        foreach ($zones->values() as $index => $zone) {
            $result = $this->callProtected($service, 'getZoneEmail', [$zone->id]);
            $this->assertEquals(
                $emails[$index],
                $result,
                "Zone {$zone->id} dovrebbe restituire {$emails[$index]}"
            );
        }
    }

    /**
     * Verifica che una variazione dell'email in DB si rifletta immediatamente
     * (nessuna cache in-memory nel metodo).
     */
    public function test_get_zone_email_reflects_db_changes_immediately(): void
    {
        $zone = Zone::first();
        $zone->update(['email' => 'before@example.com']);

        $service = $this->makeService();

        $before = $this->callProtected($service, 'getZoneEmail', [$zone->id]);
        $this->assertEquals('before@example.com', $before);

        // Aggiorna l'email direttamente nel DB
        $zone->update(['email' => 'after@example.com']);

        $after = $this->callProtected($service, 'getZoneEmail', [$zone->id]);
        $this->assertEquals('after@example.com', $after);
    }

    // ───────────────────────────────────────────
    //  FIX A-2 — getCrcEmail() legge da config
    // ───────────────────────────────────────────

    /**
     * getCrcEmail() deve restituire il valore da config('golf.emails.crc').
     */
    public function test_get_crc_email_reads_from_config(): void
    {
        $configEmail = config('golf.emails.crc');

        $result = $this->callProtected($this->makeService(), 'getCrcEmail');

        $this->assertEquals($configEmail, $result);
    }

    /**
     * getCrcEmail() deve usare il fallback se la config è null o stringa vuota.
     *
     * Il metodo usa l'operatore ?: che attiva il fallback per null, '' e false,
     * a differenza di config('key', 'default') che lo attiva solo se la chiave
     * è del tutto assente. Testiamo entrambi i casi boundary.
     */
    public function test_get_crc_email_has_sensible_fallback(): void
    {
        // Caso 1: config esplicitamente null → fallback
        config(['golf.emails.crc' => null]);
        $result = $this->callProtected($this->makeService(), 'getCrcEmail');
        $this->assertEquals('crc@federgolf.it', $result, 'null deve attivare il fallback');

        // Caso 2: config stringa vuota → fallback
        config(['golf.emails.crc' => '']);
        $result = $this->callProtected($this->makeService(), 'getCrcEmail');
        $this->assertEquals('crc@federgolf.it', $result, 'stringa vuota deve attivare il fallback');
    }

    /**
     * getCrcEmail() non deve mai restituire una stringa hardcoded
     * che sia diversa da config('golf.emails.crc') quando la config è valorizzata.
     */
    public function test_get_crc_email_does_not_bypass_config(): void
    {
        $customCrcEmail = 'custom-crc@test-domain.org';
        config(['golf.emails.crc' => $customCrcEmail]);

        $result = $this->callProtected($this->makeService(), 'getCrcEmail');

        $this->assertEquals(
            $customCrcEmail,
            $result,
            'getCrcEmail() deve rispettare la config golf.emails.crc e non usare una stringa hardcoded.'
        );
    }
}
