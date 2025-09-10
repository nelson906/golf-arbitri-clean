<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LetterTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $templates = [
            [
                'name' => 'Lettera di Assegnazione Arbitro',
                'type' => 'assignment',
                'subject' => 'Assegnazione Arbitrale - {{tournament_name}}',
                'body' => "Gentile {{user_name}},

Con la presente Le comunichiamo che è stato/a assegnato/a come {{assignment_role}} per il torneo:

**{{tournament_name}}**
Data: {{tournament_date}}
Circolo: {{club_name}}

Dettagli dell'assegnazione:
- Ruolo: {{assignment_role}}
- Compenso: €{{fee_amount}}
- Persona di contatto: {{contact_person}}

La preghiamo di confermare la Sua partecipazione entro 48 ore dalla ricezione di questa comunicazione.

Per qualsiasi chiarimento, rimaniamo a Sua disposizione.

Cordiali saluti,
Comitato Organizzatore",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => false,
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'tournament_date', 'club_name',
                    'assignment_role', 'fee_amount', 'contact_person'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Conferma Disponibilità',
                'type' => 'club',
                'subject' => 'Conferma Disponibilità - {{tournament_name}}',
                'body' => "Gentile {{user_name}},

Confermiamo di aver ricevuto la Sua dichiarazione di disponibilità per il torneo:

**{{tournament_name}}**
Stato: {{availability_status}}

Le assegnazioni verranno comunicate dopo la scadenza del termine per le dichiarazioni di disponibilità ({{deadline_date}}).

La ringraziamo per la Sua collaborazione.

Cordiali saluti,
Segreteria Tornei",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => false,
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'availability_status', 'deadline_date'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Lettera di Convocazione',
                'type' => 'convocation',
                'subject' => 'Convocazione Ufficiale - {{tournament_name}}',
                'body' => "Gentile {{user_name}},

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
Direzione Tecnica",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => false,
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'tournament_date', 'club_name',
                    'arrival_time', 'dress_code', 'special_instructions'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Reminder Scadenza',
                'type' => 'institutional',
                'subject' => 'Reminder: Scadenza Disponibilità - {{tournament_name}}',
                'body' => "Gentile {{user_name}},

Le ricordiamo che la scadenza per la dichiarazione di disponibilità per il torneo:

**{{tournament_name}}**

è fissata per il {{deadline_date}} alle ore {{deadline_time}}.

Se non ha ancora provveduto, La invitiamo a dichiarare la Sua disponibilità accedendo al sistema.

Cordiali saluti,
Sistema Automatico",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => false,
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'deadline_date', 'deadline_time'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Certificato di Partecipazione',
                'type' => 'institutional',
                'subject' => 'Certificato di Partecipazione - {{tournament_name}}',
                'body' => "CERTIFICATO DI PARTECIPAZIONE

Si certifica che

**{{user_name}}**

ha prestato servizio come {{assignment_role}} nel torneo:

**{{tournament_name}}**
svoltosi in data {{tournament_date}}

Il presente certificato è rilasciato per gli usi consentiti dalla legge.

Data: {{certificate_date}}

_____________________
Il Direttore Tecnico",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => false,
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'tournament_date',
                    'assignment_role', 'certificate_date'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Report Post-Torneo',
                'type' => 'institutional',
                'subject' => 'Report Arbitrale - {{tournament_name}}',
                'body' => "REPORT ARBITRALE

Arbitro: {{user_name}}
Torneo: {{tournament_name}}
Data: {{tournament_date}}

RIEPILOGO:
- Numero incidenti: {{incidents_count}}
- Valutazione complessiva: {{overall_rating}}

Note aggiuntive:
[Spazio per note dell'arbitro]

Firma dell'arbitro: _____________________

Data compilazione: {{report_date}}",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => false,
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'tournament_date',
                    'incidents_count', 'overall_rating', 'report_date'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Lettera di Annullamento',
                'type' => 'institutional',
                'subject' => 'Annullamento Torneo - {{tournament_name}}',
                'body' => "Gentile {{user_name}},

Con rammarico Le comunichiamo che il torneo:

**{{tournament_name}}**

è stato annullato per il seguente motivo:
{{cancellation_reason}}

{{alternative_date}}

Ci scusiamo per l'inconveniente e La ringraziamo per la comprensione.

Cordiali saluti,
Comitato Organizzatore",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => false,
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'cancellation_reason', 'alternative_date'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Convocazione Arbitro - Template Nazionale',
                'type' => 'convocation',
                'subject' => 'Convocazione per {{tournament_name}}',
                'body' => "Gentile {{user_name}},

La informiamo che è stato convocato per svolgere il ruolo di {{assignment_role}} in occasione del torneo:

**{{tournament_name}}**
Date: {{tournament_dates}}
Circolo: {{club_name}}
Zona: {{zone_name}}

La preghiamo di confermare la Sua disponibilità entro 48 ore dalla ricezione della presente.

Per ulteriori informazioni può contattare la segreteria di zona.

Cordiali saluti,
La Segreteria",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => true, // Default nazionale
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'tournament_dates',
                    'club_name', 'assignment_role', 'zone_name'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Comunicazione Circolo - Arbitri Assegnati',
                'type' => 'club',
                'subject' => 'Arbitri assegnati per {{tournament_name}}',
                'body' => "Gentile {{contact_person}},

Con la presente comunichiamo gli arbitri assegnati per il torneo:

**{{tournament_name}}**
Date: {{tournament_dates}}

**Arbitri assegnati ({{total_referees}}):**
{{referee_list}}

Gli arbitri sono stati informati della loro assegnazione.

Cordiali saluti,
La Segreteria di Zona",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => true, // Default per club
                'variables' => json_encode([
                    'tournament_name', 'tournament_dates', 'club_name',
                    'referee_list', 'total_referees', 'contact_person'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Notifica Assegnazione - Template Standard',
                'type' => 'assignment',
                'subject' => 'Nuova assegnazione: {{tournament_name}}',
                'body' => "Gentile {{user_name}},

La informiamo di essere stato assegnato al seguente torneo:

**{{tournament_name}}**
Date: {{tournament_dates}}
Circolo: {{club_name}}
Ruolo: {{assignment_role}}
Assegnato da: {{assigned_by}}

{{assignment_notes}}

La assegnazione è stata confermata automaticamente.

Cordiali saluti",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => true, // Default per assignment
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'tournament_dates',
                    'club_name', 'assignment_role', 'assigned_by', 'assignment_notes'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Convocazione SZR1 - Personalizzata',
                'type' => 'convocation',
                'subject' => 'Convocazione Zona SZR1 - {{tournament_name}}',
                'body' => "Gentile {{user_name}},

A nome della SZR1, La convoco per il torneo:

**{{tournament_name}}**
Date: {{tournament_dates}}
Circolo: {{club_name}}
Ruolo: {{assignment_role}}

Per informazioni specifiche della zona, può contattare:
{{zone_contact}}

Grazie per la Sua disponibilità.

Cordiali saluti,
La Segreteria SZR1",
                'zone_id' => 1, // SZR1
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => false,
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'tournament_dates',
                    'club_name', 'assignment_role', 'zone_name', 'zone_contact'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Convocazione SZR2 - Personalizzata',
                'type' => 'convocation',
                'subject' => 'Convocazione Zona SZR2 - {{tournament_name}}',
                'body' => "Gentile {{user_name}},

A nome della SZR2, La convoco per il torneo:

**{{tournament_name}}**
Date: {{tournament_dates}}
Circolo: {{club_name}}
Ruolo: {{assignment_role}}

Per informazioni specifiche della zona, può contattare:
{{zone_contact}}

Grazie per la Sua disponibilità.

Cordiali saluti,
La Segreteria SZR2",
                'zone_id' => 2, // SZR2
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => false,
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'tournament_dates',
                    'club_name', 'assignment_role', 'zone_name', 'zone_contact'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Template Istituzionale - Reminder Default',
                'type' => 'institutional',
                'subject' => 'Promemoria - {{tournament_name}}',
                'body' => "Gentile {{user_name}},

Questo è un promemoria automatico relativo al torneo:

**{{tournament_name}}**
Date: {{tournament_dates}}
Circolo: {{club_name}}
Zona: {{zone_name}}

Se ha domande o necessita di chiarimenti, può contattare la segreteria di zona.

Cordiali saluti,
Sistema Automatico FIG",
                'zone_id' => null,
                'tournament_type_id' => null,
                'is_active' => true,
                'is_default' => true, // Default istituzionale
                'variables' => json_encode([
                    'user_name', 'tournament_name', 'tournament_dates',
                    'club_name', 'zone_name'
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Truncate existing data
        DB::table('letter_templates')->truncate();

        // Insert new templates
        DB::table('letter_templates')->insert($templates);
    }
}
