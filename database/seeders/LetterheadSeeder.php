<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Letterhead;
use App\Models\Zone;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LetterheadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏗️ Seeding Letterheads...');

        // Clear existing letterheads if running in testing/development
        if (app()->environment(['local', 'testing'])) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            Letterhead::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // Get a super admin user for created_by
        $superAdmin = User::where('user_type', 'super_admin')->first();

        if (!$superAdmin) {
            $this->command->warn('⚠️ No super admin found. Creating letterheads without updated_by.');
        }

        // 1. Create Global Letterhead (Default for all zones)
        $this->createGlobalLetterhead($superAdmin);

        // 2. Create Zone-specific Letterheads
        $zones = Zone::all();

        if ($zones->isEmpty()) {
            $this->command->warn('⚠️ No zones found. Creating sample zones first.');
            $this->createSampleZones();
            $zones = Zone::all();
        }

        foreach ($zones as $zone) {
            $this->createZoneLetterheads($zone, $superAdmin);
        }

        // 3. Create Additional Template Varieties
        $this->createTemplateVarieties($zones, $superAdmin);

        $this->command->info('✅ Letterheads seeded successfully!');
    }

    /**
     * Create global letterhead (default for system)
     */
    private function createGlobalLetterhead(?User $superAdmin): void
    {
        $letterhead = Letterhead::create([
            'title' => 'Letterhead Globale FIG',
            'description' => 'Letterhead predefinita per la Federazione Italiana Golf',
            'zone_id' => null, // Global
            'header_text' => $this->getGlobalHeaderText(),
            'footer_text' => $this->getGlobalFooterText(),
            'contact_info' => $this->getGlobalContactInfo(),
            'settings' => $this->getDefaultSettings(),
            'is_active' => true,
            'is_default' => true, // This will be the system default
            'updated_by' => $superAdmin?->id,
        ]);

        $this->command->info("   📄 Created global letterhead: {$letterhead->title}");
    }

    /**
     * Create letterheads for a specific zone
     */
    private function createZoneLetterheads(Zone $zone, ?User $superAdmin): void
    {
        // Default letterhead for zone
        $defaultLetterhead = Letterhead::create([
            'title' => "Letterhead {$zone->name}",
            'description' => "Letterhead ufficiale per la zona {$zone->name}",
            'zone_id' => $zone->id,
            'header_text' => $this->getZoneHeaderText($zone),
            'footer_text' => $this->getZoneFooterText($zone),
            'contact_info' => $this->getZoneContactInfo($zone),
            'settings' => $this->getDefaultSettings(),
            'is_active' => true,
            'is_default' => true, // Default for this zone
            'updated_by' => $superAdmin?->id,
        ]);

        // Alternative letterhead for special occasions
        $alternativeLetterhead = Letterhead::create([
            'title' => "Letterhead Formale {$zone->name}",
            'description' => "Letterhead per comunicazioni ufficiali zona {$zone->name}",
            'zone_id' => $zone->id,
            'header_text' => $this->getFormalHeaderText($zone),
            'footer_text' => $this->getFormalFooterText($zone),
            'contact_info' => $this->getZoneContactInfo($zone),
            'settings' => $this->getFormalSettings(),
            'is_active' => true,
            'is_default' => false,
            'updated_by' => $superAdmin?->id,
        ]);

        $this->command->info("   📄 Created letterheads for zone: {$zone->name}");
    }

    /**
     * Create template varieties for testing and examples
     */
    private function createTemplateVarieties($zones, ?User $superAdmin): void
    {
        $sampleZone = $zones->first();

        if (!$sampleZone) {
            return;
        }

        // Minimal letterhead
        Letterhead::create([
            'title' => 'Letterhead Minimale',
            'description' => 'Template minimale per comunicazioni veloci',
            'zone_id' => $sampleZone->id,
            'header_text' => 'Golf Referee Management',
            'footer_text' => null,
            'contact_info' => ['email' => 'arbitri@golf.it'],
            'settings' => $this->getMinimalSettings(),
            'is_active' => true,
            'is_default' => false,
            'updated_by' => $superAdmin?->id,
        ]);

        // Professional letterhead with custom styling
        Letterhead::create([
            'title' => 'Letterhead Professionale',
            'description' => 'Template professionale per documenti ufficiali',
            'zone_id' => $sampleZone->id,
            'header_text' => $this->getProfessionalHeaderText($sampleZone),
            'footer_text' => $this->getProfessionalFooterText(),
            'contact_info' => $this->getProfessionalContactInfo(),
            'settings' => $this->getProfessionalSettings(),
            'is_active' => true,
            'is_default' => false,
            'updated_by' => $superAdmin?->id,
        ]);

        // Inactive letterhead for testing
        Letterhead::create([
            'title' => 'Letterhead Disattivata',
            'description' => 'Template per test - disattivata',
            'zone_id' => $sampleZone->id,
            'header_text' => 'Template di Test',
            'footer_text' => 'Questo template è disattivato',
            'contact_info' => [],
            'settings' => $this->getDefaultSettings(),
            'is_active' => false, // Inactive
            'is_default' => false,
            'updated_by' => $superAdmin?->id,
        ]);

        $this->command->info("   📄 Created template varieties for testing");
    }

    /**
     * Create sample zones if none exist
     */
    private function createSampleZones(): void
    {
        $zones = [
            ['name' => 'Lombardia', 'code' => 'LOM', 'description' => 'Zona Lombardia'],
            ['name' => 'Lazio', 'code' => 'LAZ', 'description' => 'Zona Lazio'],
            ['name' => 'Veneto', 'code' => 'VEN', 'description' => 'Zona Veneto'],
        ];

        foreach ($zones as $zoneData) {
            Zone::create($zoneData);
        }

        $this->command->info('   🌍 Created sample zones');
    }

    // Content Generation Methods

    private function getGlobalHeaderText(): string
    {
        return "FEDERAZIONE ITALIANA GOLF\nComitato Nazionale Arbitri\n\nSistema di Gestione Arbitri";
    }

    private function getGlobalFooterText(): string
    {
        return "Documento generato automaticamente dal Sistema di Gestione Arbitri FIG\nPer informazioni: arbitri@federgolf.it";
    }

    private function getGlobalContactInfo(): array
    {
        return [
            'address' => 'Via Flaminia, 388 - 00196 Roma',
            'phone' => '+39 06 32329825',
            'email' => 'arbitri@federgolf.it',
            'website' => 'www.federgolf.it',
        ];
    }

    private function getZoneHeaderText(Zone $zone): string
    {
        return "FEDERAZIONE ITALIANA GOLF\nComitato Regionale {$zone->name}\nSezione Zonale Regole\n\nProt. n. _____ del {{date}}";
    }

    private function getZoneFooterText(Zone $zone): string
    {
        return "Comitato Regionale {$zone->name} - Sezione Zonale Regole\nPer informazioni: szr.{$zone->code}@federgolf.it";
    }

    private function getZoneContactInfo(Zone $zone): array
    {
        $cities = [
            'LOM' => ['city' => 'Milano', 'cap' => '20100'],
            'LAZ' => ['city' => 'Roma', 'cap' => '00100'],
            'VEN' => ['city' => 'Venezia', 'cap' => '30100'],
        ];

        $cityInfo = $cities[$zone->code] ?? ['city' => 'Città', 'cap' => '00000'];

        return [
            'address' => "Via del Golf, 123 - {$cityInfo['cap']} {$cityInfo['city']}",
            'phone' => '+39 ' . rand(10, 99) . ' ' . rand(1000000, 9999999),
            'email' => "szr.{$zone->code}@federgolf.it",
            'website' => "www.federgolf.it/{$zone->code}",
        ];
    }

    private function getFormalHeaderText(Zone $zone): string
    {
        return "FEDERAZIONE ITALIANA GOLF\nCOMITATO REGIONALE {$zone->name}\nSETTORE TECNICO ARBITRALE\n\nOGGETTO: {{tournament_name}}\nRIF: Prot. n. _____ del {{date}}";
    }

    private function getFormalFooterText(Zone $zone): string
    {
        return "Il presente documento ha valore ufficiale.\nComitato Regionale {$zone->name} - Settore Tecnico Arbitrale\nFederazione Italiana Golf";
    }

    private function getProfessionalHeaderText(Zone $zone): string
    {
        return "FEDERAZIONE ITALIANA GOLF\nCOMITATO REGIONALE {$zone->name}\n\n" .
               "COMUNICAZIONE UFFICIALE\n" .
               "Prot. n. {{protocol_number}} del {{date}}\n" .
               "Oggetto: {{subject}}";
    }

    private function getProfessionalFooterText(): string
    {
        return "La presente comunicazione è stata generata dal Sistema di Gestione Arbitri FIG.\n" .
               "Documento con valore ufficiale ai sensi del Regolamento FIG.\n" .
               "{{zone_name}} - {{contact_email}} - {{contact_phone}}";
    }

    private function getProfessionalContactInfo(): array
    {
        return [
            'address' => 'Sede Regionale Golf, Via dei Campi 456 - 00100 Roma',
            'phone' => '+39 06 12345678',
            'email' => 'ufficiale@federgolf.it',
            'website' => 'www.federgolf.it/ufficiale',
        ];
    }

    // Settings Generation Methods

    private function getDefaultSettings(): array
    {
        return [
            'margins' => [
                'top' => 20,
                'bottom' => 20,
                'left' => 25,
                'right' => 25,
            ],
            'font' => [
                'family' => 'Arial',
                'size' => 11,
                'color' => '#000000',
            ],
            'letterhead' => [
                'header_alignment' => 'left',
                'footer_alignment' => 'center',
                'show_page_numbers' => false,
            ],
        ];
    }

    private function getFormalSettings(): array
    {
        return [
            'margins' => [
                'top' => 30,
                'bottom' => 25,
                'left' => 30,
                'right' => 30,
            ],
            'font' => [
                'family' => 'Times New Roman',
                'size' => 12,
                'color' => '#000000',
            ],
            'letterhead' => [
                'header_alignment' => 'center',
                'footer_alignment' => 'center',
                'show_page_numbers' => true,
                'formal_spacing' => true,
            ],
        ];
    }

    private function getMinimalSettings(): array
    {
        return [
            'margins' => [
                'top' => 15,
                'bottom' => 15,
                'left' => 20,
                'right' => 20,
            ],
            'font' => [
                'family' => 'Arial',
                'size' => 10,
                'color' => '#333333',
            ],
            'letterhead' => [
                'header_alignment' => 'left',
                'footer_alignment' => 'left',
                'minimal_style' => true,
            ],
        ];
    }

    private function getProfessionalSettings(): array
    {
        return [
            'margins' => [
                'top' => 25,
                'bottom' => 20,
                'left' => 25,
                'right' => 25,
            ],
            'font' => [
                'family' => 'Times New Roman',
                'size' => 11,
                'color' => '#000000',
            ],
            'letterhead' => [
                'header_alignment' => 'center',
                'footer_alignment' => 'center',
                'show_page_numbers' => true,
                'professional_style' => true,
                'header_border' => true,
                'footer_border' => true,
            ],
        ];
    }
}
