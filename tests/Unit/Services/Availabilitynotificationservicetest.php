<?php

namespace Tests\Unit\Services;

use App\Models\Availability;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Services\AvailabilityNotificationService;
use Tests\TestCase;

class AvailabilityNotificationServiceTest extends TestCase
{
    protected AvailabilityNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AvailabilityNotificationService;
    }

    // ==========================================
    // GROUPING TESTS
    // ==========================================

    /**
     * Test: Raggruppa correttamente per tipo torneo
     */
    public function test_groups_availabilities_by_tournament_type(): void
    {
        $referee = $this->createReferee();

        $zonalType = TournamentType::where('is_national', false)->first();
        $nationalType = TournamentType::where('is_national', true)->first();

        // 2 tornei zonali
        $zonal1 = Tournament::factory()->create(['tournament_type_id' => $zonalType->id]);
        $zonal2 = Tournament::factory()->create(['tournament_type_id' => $zonalType->id]);

        // 1 torneo nazionale
        $national1 = Tournament::factory()->create(['tournament_type_id' => $nationalType->id]);

        $availabilities = collect([
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $zonal1->id, 'submitted_at' => now()]),
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $zonal2->id, 'submitted_at' => now()]),
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $national1->id, 'submitted_at' => now()]),
        ]);

        $grouped = $this->service->groupByTournamentType($availabilities);

        $this->assertArrayHasKey('zonal', $grouped);
        $this->assertArrayHasKey('national', $grouped);
        $this->assertCount(2, $grouped['zonal']);
        $this->assertCount(1, $grouped['national']);
    }

    /**
     * Test: Gestisce correttamente solo disponibilità zonali
     */
    public function test_groups_only_zonal_availabilities(): void
    {
        $referee = $this->createReferee();
        $zonalType = TournamentType::where('is_national', false)->first();

        $zonal1 = Tournament::factory()->create(['tournament_type_id' => $zonalType->id]);
        $zonal2 = Tournament::factory()->create(['tournament_type_id' => $zonalType->id]);

        $availabilities = collect([
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $zonal1->id, 'submitted_at' => now()]),
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $zonal2->id, 'submitted_at' => now()]),
        ]);

        $grouped = $this->service->groupByTournamentType($availabilities);

        $this->assertCount(2, $grouped['zonal']);
        $this->assertCount(0, $grouped['national']);
    }

    /**
     * Test: Gestisce correttamente solo disponibilità nazionali
     */
    public function test_groups_only_national_availabilities(): void
    {
        $referee = $this->createReferee();
        $nationalType = TournamentType::where('is_national', true)->first();

        $national1 = Tournament::factory()->create(['tournament_type_id' => $nationalType->id]);
        $national2 = Tournament::factory()->create(['tournament_type_id' => $nationalType->id]);

        $availabilities = collect([
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $national1->id, 'submitted_at' => now()]),
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $national2->id, 'submitted_at' => now()]),
        ]);

        $grouped = $this->service->groupByTournamentType($availabilities);

        $this->assertCount(0, $grouped['zonal']);
        $this->assertCount(2, $grouped['national']);
    }

    // ==========================================
    // RECIPIENTS DETERMINATION TESTS
    // ==========================================

    /**
     * Test: Determina destinatari corretti per disponibilità miste
     */
    public function test_determines_recipients_for_mixed_availabilities(): void
    {
        $referee = User::factory()->referee()->inZone(3)->create();

        $zonalType = TournamentType::where('is_national', false)->first();
        $nationalType = TournamentType::where('is_national', true)->first();

        $zonal = Tournament::factory()->create(['tournament_type_id' => $zonalType->id]);
        $national = Tournament::factory()->create(['tournament_type_id' => $nationalType->id]);

        $availabilities = collect([
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $zonal->id, 'submitted_at' => now()]),
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $national->id, 'submitted_at' => now()]),
        ]);

        $recipients = $this->service->determineRecipients($referee, $availabilities);

        // Verifica struttura
        $this->assertArrayHasKey('zone', $recipients);
        $this->assertArrayHasKey('crc', $recipients);
        $this->assertArrayHasKey('referee', $recipients);

        // Zona riceve solo zonali
        $this->assertNotNull($recipients['zone']);
        $this->assertEquals('szr3@federgolf.it', $recipients['zone']['email']);
        $this->assertCount(1, $recipients['zone']['availabilities']);

        // CRC riceve solo nazionali
        $this->assertNotNull($recipients['crc']);
        $this->assertEquals('crc@federgolf.it', $recipients['crc']['email']);
        $this->assertCount(1, $recipients['crc']['availabilities']);

        // Arbitro riceve tutti
        $this->assertCount(2, $recipients['referee']['availabilities']);
    }

    /**
     * Test: Solo disponibilità zonali - CRC non riceve
     */
    public function test_determines_only_zone_recipient_for_zonal_availabilities(): void
    {
        $referee = User::factory()->referee()->inZone(3)->create();
        $zonalType = TournamentType::where('is_national', false)->first();

        $zonal = Tournament::factory()->create(['tournament_type_id' => $zonalType->id]);

        $availabilities = collect([
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $zonal->id, 'submitted_at' => now()]),
        ]);

        $recipients = $this->service->determineRecipients($referee, $availabilities);

        // Zona riceve
        $this->assertNotNull($recipients['zone']);
        $this->assertCount(1, $recipients['zone']['availabilities']);

        // CRC NON riceve
        $this->assertNull($recipients['crc']);

        // Arbitro riceve
        $this->assertCount(1, $recipients['referee']['availabilities']);
    }

    /**
     * Test: Solo disponibilità nazionali - Zona non riceve
     */
    public function test_determines_only_crc_recipient_for_national_availabilities(): void
    {
        $referee = User::factory()->referee()->inZone(3)->create();
        $nationalType = TournamentType::where('is_national', true)->first();

        $national = Tournament::factory()->create(['tournament_type_id' => $nationalType->id]);

        $availabilities = collect([
            Availability::create(['user_id' => $referee->id, 'tournament_id' => $national->id, 'submitted_at' => now()]),
        ]);

        $recipients = $this->service->determineRecipients($referee, $availabilities);

        // Zona NON riceve
        $this->assertNull($recipients['zone']);

        // CRC riceve
        $this->assertNotNull($recipients['crc']);
        $this->assertCount(1, $recipients['crc']['availabilities']);

        // Arbitro riceve
        $this->assertCount(1, $recipients['referee']['availabilities']);
    }

    // ==========================================
    // EMAIL ADDRESS TESTS
    // ==========================================

    /**
     * Test: Email zona corrette per tutte le zone
     */
    public function test_zone_emails_are_correct(): void
    {
        $testCases = [
            1 => 'szr1@federgolf.it',
            2 => 'szr2@federgolf.it',
            3 => 'szr3@federgolf.it',
            4 => 'szr4@federgolf.it',
            5 => 'szr5@federgolf.it',
            6 => 'szr6@federgolf.it',
            7 => 'szr7@federgolf.it',
        ];

        foreach ($testCases as $zoneId => $expectedEmail) {
            $referee = User::factory()->referee()->inZone($zoneId)->create();
            $zonalType = TournamentType::where('is_national', false)->first();
            $zonal = Tournament::factory()->create(['tournament_type_id' => $zonalType->id]);

            $availabilities = collect([
                Availability::create(['user_id' => $referee->id, 'tournament_id' => $zonal->id, 'submitted_at' => now()]),
            ]);

            $recipients = $this->service->determineRecipients($referee, $availabilities);

            $this->assertEquals($expectedEmail, $recipients['zone']['email'], "Zone $zoneId email mismatch");
        }
    }

    // ==========================================
    // EDGE CASES
    // ==========================================

    /**
     * Test: Gestisce collection vuota
     */
    public function test_handles_empty_availabilities(): void
    {
        $referee = $this->createReferee();
        $availabilities = collect();

        $recipients = $this->service->determineRecipients($referee, $availabilities);

        $this->assertNull($recipients['zone']);
        $this->assertNull($recipients['crc']);
        $this->assertCount(0, $recipients['referee']['availabilities']);
    }
}
