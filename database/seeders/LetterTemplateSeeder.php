<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LetterTemplate;
use Illuminate\Support\Facades\Schema;

class LetterTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📄 Creando Template Lettere Sistema...');

        // Elimina template esistenti per evitare duplicati
        if (Schema::hasTable('letter_templates')) {
            LetterTemplate::truncate();
        } else {
            $this->command->warn('⚠️ Tabella letter_templates non trovata - saltando seeder');
            return;
        }

        $templates = $this->getTemplateDefinitions();
        $created = 0;

        foreach ($templates as $templateData) {
            $template = LetterTemplate::create($templateData);
            $this->command->info("✅ Template creato: {$template->name}");
            $created++;
        }

        $this->command->info("🏆 Template creati con successo: {$created} template totali");
    }

    /**
     * Definizioni dei template
     */
    private function getTemplateDefinitions(): array
    {
        return [
            [
                'name' => 'Lettera di Assegnazione Arbitro',
                // 'code' => 'ASSIGNMENT_LETTER',
                'description' => 'federazione',
                'subject' => 'Assegnazione Arbitrale - {{tournament_name}}',
                'body' => $this->getAssignmentLetterBody(),
                'type' => 'assignment',
                'is_active' => true,
                'variables' => json_encode([
                    'referee_name',
                    'tournament_name',
                    'tournament_date',
                    'club_name',
                    'role',
                    'fee_amount',
                    'contact_person'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Conferma Disponibilità',
                // 'code' => 'AVAILABILITY_CONFIRMATION',
                'description' => 'altro',
                'subject' => 'Conferma Disponibilità - {{tournament_name}}',
                'body' => $this->getAvailabilityConfirmationBody(),
                'type' => 'club',
                'is_active' => true,
                'variables' => json_encode([
                    'referee_name',
                    'tournament_name',
                    'availability_status',
                    'deadline_date'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Lettera di Convocazione',
                // 'code' => 'CONVOCATION_LETTER',
                'description' => 'altro',
                'subject' => 'Convocazione Ufficiale - {{tournament_name}}',
                'body' => $this->getConvocationLetterBody(),
                'type' => 'convocation',
                'is_active' => true,
                'variables' => json_encode([
                    'referee_name',
                    'tournament_name',
                    'tournament_date',
                    'club_name',
                    'arrival_time',
                    'dress_code',
                    'special_instructions'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Reminder Scadenza',
                // 'code' => 'DEADLINE_REMINDER',
                'description' => 'altro',
                'subject' => 'Reminder: Scadenza Disponibilità - {{tournament_name}}',
                'body' => $this->getDeadlineReminderBody(),
                'type' => 'institutional',
                'is_active' => true,
                'variables' => json_encode([
                    'referee_name',
                    'tournament_name',
                    'deadline_date',
                    'deadline_time'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Certificato di Partecipazione',
                // 'code' => 'PARTICIPATION_CERTIFICATE',
                'description' => 'Certificato di partecipazione come arbitro',
                'subject' => 'institutional',
                'body' => $this->getParticipationCertificateBody(),
                'type' => 'institutional',
                'is_active' => true,
                'variables' => json_encode([
                    'referee_name',
                    'tournament_name',
                    'tournament_date',
                    'role',
                    'certificate_date'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Report Post-Torneo',
                // 'code' => 'POST_TOURNAMENT_REPORT',
                'description' => 'Template per report arbitrale post-torneo',
                'subject' => 'altro',
                'body' => $this->getPostTournamentReportBody(),
                'type' => 'institutional',
                'is_active' => true,
                'variables' => json_encode([
                    'referee_name',
                    'tournament_name',
                    'tournament_date',
                    'incidents_count',
                    'overall_rating'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Lettera di Annullamento',
                // 'code' => 'CANCELLATION_LETTER',
                'description' => 'Comunicazione annullamento',
                'subject' => 'altro',
                'body' => $this->getCancellationLetterBody(),
                'type' => 'institutional',
                'is_active' => true,
                'variables' => json_encode([
                    'referee_name',
                    'tournament_name',
                    'cancellation_reason',
                    'alternative_date'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];
    }

    /**
     * Template lettera di assegnazione
     */
    private function getAssignmentLetterBody(): string
    {
        return "
Gentile {{referee_name}},

Con la presente Le comunichiamo che è stato/a assegnato/a come {{role}} per il torneo:

**{{tournament_name}}**
Data: {{tournament_date}}
Circolo: {{club_name}}

Dettagli dell'assegnazione:
- Ruolo: {{role}}
- Compenso: €{{fee_amount}}
- Persona di contatto: {{contact_person}}

La preghiamo di confermare la Sua partecipazione entro 48 ore dalla ricezione di questa comunicazione.

Per qualsiasi chiarimento, rimaniamo a Sua disposizione.

Cordiali saluti,
Comitato Organizzatore
";
    }

    /**
     * Template conferma disponibilità
     */
    private function getAvailabilityConfirmationBody(): string
    {
        return "
Gentile {{referee_name}},

Confermiamo di aver ricevuto la Sua dichiarazione di disponibilità per il torneo:

**{{tournament_name}}**
Stato: {{availability_status}}

Le assegnazioni verranno comunicate dopo la scadenza del termine per le dichiarazioni di disponibilità ({{deadline_date}}).

La ringraziamo per la Sua collaborazione.

Cordiali saluti,
Segreteria Tornei
";
    }

    /**
     * Template lettera di convocazione
     */
    private function getConvocationLetterBody(): string
    {
        return "
Gentile {{referee_name}},

È convocato/a ufficialmente per prestare servizio nel torneo:

**{{tournament_name}}**
Data: {{tournament_date}}
Circolo: {{club_name}}
Orario arrivo: {{arrival_time}}

ISTRUZIONI IMPORTANTI:
- Abbigliamento: {{dress_code}}
- {{special_instructions}}

Si ricorda che la partecipazione è obbligatoria, salvo gravi e documentati impedimenti.

Cordiali saluti,
Direzione Tecnica
";
    }

    /**
     * Template reminder scadenza
     */
    private function getDeadlineReminderBody(): string
    {
        return "
Gentile {{referee_name}},

Le ricordiamo che la scadenza per la dichiarazione di disponibilità per il torneo:

**{{tournament_name}}**

è fissata per il {{deadline_date}} alle ore {{deadline_time}}.

Se non ha ancora provveduto, La invitiamo a dichiarare la Sua disponibilità accedendo al sistema.

Cordiali saluti,
Sistema Automatico
";
    }

    /**
     * Template certificato partecipazione
     */
    private function getParticipationCertificateBody(): string
    {
        return "
CERTIFICATO DI PARTECIPAZIONE

Si certifica che

**{{referee_name}}**

ha prestato servizio come {{role}} nel torneo:

**{{tournament_name}}**
svoltosi in data {{tournament_date}}

Il presente certificato è rilasciato per gli usi consentiti dalla legge.

Data: {{certificate_date}}

_____________________
Il Direttore Tecnico
";
    }

    /**
     * Template report post-torneo
     */
    private function getPostTournamentReportBody(): string
    {
        return "
REPORT ARBITRALE

Arbitro: {{referee_name}}
Torneo: {{tournament_name}}
Data: {{tournament_date}}

RIEPILOGO:
- Numero incidenti: {{incidents_count}}
- Valutazione complessiva: {{overall_rating}}

Note aggiuntive:
[Spazio per note dell'arbitro]

Firma dell'arbitro: _____________________

Data compilazione: {{report_date}}
";
    }

    /**
     * Template lettera annullamento
     */
    private function getCancellationLetterBody(): string
    {
        return "
Gentile {{referee_name}},

Con rammarico Le comunichiamo che il torneo:

**{{tournament_name}}**

è stato annullato per il seguente motivo:
{{cancellation_reason}}

{{alternative_date}}

Ci scusiamo per l'inconveniente e La ringraziamo per la comprensione.

Cordiali saluti,
Comitato Organizzatore
";
    }
}
