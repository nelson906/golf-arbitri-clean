<?php

namespace Tests\Unit\Enums;

use App\Enums\UserType;
use App\Models\User;
use Tests\TestCase;

/**
 * Test di regressione — Bug: query raw usavano la stringa letterale 'admin'
 * invece di UserType::ZoneAdmin->value per filtrare gli admin di zona.
 *
 * Rischio: se il valore DB dell'enum cambia, o se un altro sviluppatore
 * usa la stringa sbagliata, la query restituisce risultati vuoti silenziosamente.
 *
 * Fix: sostituzione di 'admin' con UserType::ZoneAdmin->value nelle query
 * in NotificationController e AvailabilityController.
 *
 * @see app/Http/Controllers/Admin/NotificationController.php
 * @see app/Http/Controllers/User/AvailabilityController.php
 */
class UserTypeZoneAdminQueryTest extends TestCase
{
    // ============================================================
    // SEZIONE 1 — Contratto dell'Enum (non deve mai cambiare senza test)
    // ============================================================

    /**
     * Il valore DB di ZoneAdmin DEVE essere 'admin'.
     * Se questo test rompe, significa che qualcuno ha cambiato il valore
     * dell'enum senza aggiornare le query raw residue nel codice.
     */
    public function test_zone_admin_enum_value_is_admin_string(): void
    {
        $this->assertSame(
            'admin',
            UserType::ZoneAdmin->value,
            "UserType::ZoneAdmin->value deve essere 'admin' (valore nel DB). " .
            "Se lo cambi, devi aggiornare anche le migrazioni del DB."
        );
    }

    /**
     * La query che usa UserType::ZoneAdmin->value è equivalente alla stringa 'admin'.
     * Questo test documenta esplicitamente la relazione.
     */
    public function test_zone_admin_value_equals_string_admin(): void
    {
        $this->assertSame('admin', UserType::ZoneAdmin->value);
        $this->assertNotSame('zone_admin', UserType::ZoneAdmin->value);
        $this->assertNotSame('ZoneAdmin', UserType::ZoneAdmin->value);
    }

    // ============================================================
    // SEZIONE 2 — Query DB: trovare ZoneAdmin usando l'enum
    // ============================================================

    /**
     * Regressione: una query con UserType::ZoneAdmin->value deve trovare
     * gli stessi admin che trovava la query con la stringa 'admin'.
     * Il fix garantisce che le due forme siano equivalenti.
     */
    public function test_query_with_enum_value_finds_zone_admins(): void
    {
        $zone1 = \App\Models\Zone::first();
        $this->assertNotNull($zone1, 'Devono esistere zone per questo test');

        // Crea admin di zona
        $zoneAdmin = $this->createZoneAdmin($zone1->id);

        // Query con enum value (forma corretta dopo il fix)
        $foundViaEnum = User::where('user_type', UserType::ZoneAdmin->value)
            ->where('zone_id', $zone1->id)
            ->where('is_active', true)
            ->get();

        // Query con stringa letterale (forma legacy)
        $foundViaString = User::where('user_type', 'admin')
            ->where('zone_id', $zone1->id)
            ->where('is_active', true)
            ->get();

        // Devono trovare gli stessi utenti
        $this->assertCount(1, $foundViaEnum, 'La query con enum deve trovare 1 ZoneAdmin');
        $this->assertCount(1, $foundViaString, 'La query con stringa deve trovare 1 ZoneAdmin');
        $this->assertEquals(
            $foundViaEnum->pluck('id')->sort()->values(),
            $foundViaString->pluck('id')->sort()->values(),
            'Le due forme di query devono restituire gli stessi risultati'
        );
        $this->assertEquals($zoneAdmin->id, $foundViaEnum->first()->id);
    }

    /**
     * La query ZoneAdmin NON deve trovare referee o national_admin.
     */
    public function test_query_with_zone_admin_value_excludes_other_roles(): void
    {
        $zone = \App\Models\Zone::first();

        $zoneAdmin    = $this->createZoneAdmin($zone->id);
        $referee      = $this->createReferee(['zone_id' => $zone->id]);
        $nationalAdmin = $this->createNationalAdmin(['zone_id' => $zone->id]);

        $zoneAdmins = User::where('user_type', UserType::ZoneAdmin->value)
            ->where('zone_id', $zone->id)
            ->get();

        $ids = $zoneAdmins->pluck('id');

        $this->assertTrue($ids->contains($zoneAdmin->id),
            'ZoneAdmin deve essere nella lista');
        $this->assertFalse($ids->contains($referee->id),
            'Referee NON deve essere nella lista');
        $this->assertFalse($ids->contains($nationalAdmin->id),
            'NationalAdmin NON deve essere nella lista');
    }

    /**
     * La query degli admin di zona per notifiche deve funzionare
     * esattamente come il codice corretto nel NotificationController (dopo fix).
     */
    public function test_notification_controller_zone_admin_query_pattern(): void
    {
        $zone = \App\Models\Zone::first();

        // Crea scenario: 2 admin attivi + 1 inattivo
        $activeAdmin1  = $this->createZoneAdmin($zone->id, ['is_active' => true]);
        $activeAdmin2  = $this->createZoneAdmin($zone->id, ['is_active' => true]);
        $inactiveAdmin = $this->createZoneAdmin($zone->id, ['is_active' => false]);

        // Pattern CORRETTO (dopo il fix) con UserType enum
        $zoneAdmins = User::where('user_type', UserType::ZoneAdmin->value)
            ->where('zone_id', $zone->id)
            ->where('is_active', true)
            ->get();

        $this->assertCount(2, $zoneAdmins,
            'Deve trovare solo i 2 admin attivi, non quello inattivo');
        $this->assertTrue($zoneAdmins->contains('id', $activeAdmin1->id));
        $this->assertTrue($zoneAdmins->contains('id', $activeAdmin2->id));
        $this->assertFalse($zoneAdmins->contains('id', $inactiveAdmin->id));
    }

    // ============================================================
    // SEZIONE 3 — Pattern pluck per email (AvailabilityController)
    // ============================================================

    /**
     * Regressione per AvailabilityController::collectZoneAdminEmails().
     * La versione corretta usa UserType::ZoneAdmin->value al posto di 'admin'.
     */
    public function test_availability_controller_zone_admin_email_query_pattern(): void
    {
        $zone = \App\Models\Zone::first();

        $admin = $this->createZoneAdmin($zone->id, [
            'is_active' => true,
            'email' => 'zoneadmin@test.com',
        ]);

        // Pattern CORRETTO (dopo il fix) — pluck email
        $emails = User::where('zone_id', $zone->id)
            ->where('user_type', UserType::ZoneAdmin->value)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->toArray();

        $this->assertContains('zoneadmin@test.com', $emails,
            'L\'email dello ZoneAdmin deve essere presente');
    }
}
