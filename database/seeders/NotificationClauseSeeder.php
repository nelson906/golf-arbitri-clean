<?php

namespace Database\Seeders;

use App\Models\NotificationClause;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationClauseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clauses = [
            // CATEGORIA: SPESE
            [
                'code' => 'spese_medico',
                'category' => 'spese',
                'title' => 'Spese Servizio Medico',
                'content' => 'Il circolo organizzatore dovrà farsi carico delle spese per il servizio medico obbligatorio come da regolamento FIG. L\'importo stimato è di €300,00 per l\'intera durata della manifestazione.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'spese_viaggio',
                'category' => 'spese',
                'title' => 'Rimborso Spese Viaggio',
                'content' => 'Il circolo rimborserà agli arbitri le spese di viaggio secondo il tariffario FIG vigente (€0,35/km per utilizzo mezzo proprio). Il rimborso dovrà essere liquidato entro 30 giorni dalla conclusione della manifestazione.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'spese_vitto',
                'category' => 'spese',
                'title' => 'Vitto Incluso',
                'content' => 'Il circolo fornirà il vitto agli arbitri per l\'intera durata della manifestazione presso le strutture del circolo stesso. I pasti comprenderanno pranzo e cena per le giornate di gara.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'spese_alloggio',
                'category' => 'spese',
                'title' => 'Alloggio Fornito',
                'content' => 'Il circolo fornirà l\'alloggio agli arbitri presso strutture convenzionate. L\'alloggio comprenderà camera singola e prima colazione per le notti necessarie alla copertura della manifestazione.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'code' => 'spese_nessuna',
                'category' => 'spese',
                'title' => 'Nessuna Spesa Aggiuntiva',
                'content' => 'Non sono previste spese aggiuntive a carico del circolo organizzatore oltre a quelle già stabilite dal regolamento federale.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'code' => 'spese_pacchetto_completo',
                'category' => 'spese',
                'title' => 'Pacchetto Completo',
                'content' => 'Il circolo si farà carico di vitto, alloggio, servizio medico e rimborso spese viaggio per tutti gli arbitri designati. Tutti i servizi saranno forniti presso le strutture del circolo o presso strutture convenzionate.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 6,
            ],

            // CATEGORIA: LOGISTICA
            [
                'code' => 'logistica_parcheggio',
                'category' => 'logistica',
                'title' => 'Parcheggio Gratuito',
                'content' => 'Il circolo mette a disposizione degli arbitri un parcheggio gratuito riservato nelle immediate vicinanze della club house. I posti auto riservati saranno segnalati.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'logistica_spogliatoio',
                'category' => 'logistica',
                'title' => 'Spogliatoio Dedicato',
                'content' => 'Il circolo fornirà uno spogliatoio dedicato agli arbitri con docce, armadietti e servizi igienici. Lo spogliatoio sarà disponibile da un\'ora prima dell\'inizio delle gare fino alla conclusione delle operazioni giornaliere.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 11,
            ],
            [
                'code' => 'logistica_sala_riunioni',
                'category' => 'logistica',
                'title' => 'Sala Riunioni Disponibile',
                'content' => 'Il circolo mette a disposizione una sala riunioni per il briefing pre-gara e per eventuali riunioni degli arbitri. La sala sarà dotata di proiettore e lavagna per eventuali necessità organizzative.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 12,
            ],
            [
                'code' => 'logistica_transfer',
                'category' => 'logistica',
                'title' => 'Servizio Transfer',
                'content' => 'Il circolo organizzerà un servizio transfer dalla stazione ferroviaria/aeroporto più vicina al circolo e ritorno. Gli arbitri dovranno comunicare gli orari di arrivo con almeno 48 ore di anticipo.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 13,
            ],

            // CATEGORIA: RESPONSABILITÀ
            [
                'code' => 'resp_assicurazione_standard',
                'category' => 'responsabilita',
                'title' => 'Copertura Assicurativa Standard FIG',
                'content' => 'Gli arbitri sono coperti dalla polizza assicurativa standard della Federazione Italiana Golf per responsabilità civile e infortuni durante l\'espletamento delle funzioni arbitrali.',
                'applies_to' => 'all',
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'resp_assicurazione_estesa',
                'category' => 'responsabilita',
                'title' => 'Copertura Assicurativa Estesa',
                'content' => 'Gli arbitri sono coperti da una polizza assicurativa estesa che comprende responsabilità civile, infortuni, assistenza sanitaria e tutela legale per tutta la durata della manifestazione, inclusi i trasferimenti da/per il circolo.',
                'applies_to' => 'all',
                'is_active' => true,
                'sort_order' => 21,
            ],
            [
                'code' => 'resp_circolo_strutture',
                'category' => 'responsabilita',
                'title' => 'Responsabilità Circolo',
                'content' => 'Il circolo organizzatore si assume la piena responsabilità per eventuali danni alle strutture del circolo e per la sicurezza degli arbitri durante la permanenza presso le proprie installazioni.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 22,
            ],

            // CATEGORIA: COMUNICAZIONI
            [
                'code' => 'com_report_obbligatorio',
                'category' => 'comunicazioni',
                'title' => 'Report Post-Gara Obbligatorio',
                'content' => 'Gli arbitri sono tenuti a compilare e inviare il report post-gara entro 48 ore dalla conclusione della manifestazione utilizzando il modulo FIG standard. Il report dovrà essere inviato via email alla Segreteria Zonale di competenza.',
                'applies_to' => 'referee',
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'code' => 'com_referente_circolo',
                'category' => 'comunicazioni',
                'title' => 'Referente Circolo Sempre Disponibile',
                'content' => 'Il circolo garantisce la presenza di un referente sempre disponibile per gli arbitri durante tutta la durata della manifestazione. I recapiti del referente saranno comunicati al momento dell\'arrivo al circolo.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 31,
            ],
            [
                'code' => 'com_gruppo_whatsapp',
                'category' => 'comunicazioni',
                'title' => 'Gruppo WhatsApp Dedicato',
                'content' => 'Sarà creato un gruppo WhatsApp dedicato alla manifestazione per facilitare le comunicazioni tra arbitri, circolo e Segreteria Zonale. Il gruppo sarà attivo da 48 ore prima dell\'inizio della manifestazione fino alla sua conclusione.',
                'applies_to' => 'all',
                'is_active' => true,
                'sort_order' => 32,
            ],
            [
                'code' => 'com_briefing_obbligatorio',
                'category' => 'comunicazioni',
                'title' => 'Briefing Pre-Gara Obbligatorio',
                'content' => 'Gli arbitri sono tenuti a partecipare al briefing pre-gara che si terrà il giorno precedente l\'inizio della manifestazione. L\'orario e il luogo del briefing saranno comunicati via email almeno 3 giorni prima.',
                'applies_to' => 'referee',
                'is_active' => true,
                'sort_order' => 33,
            ],

            // CATEGORIA: ALTRO
            [
                'code' => 'altro_dress_code',
                'category' => 'altro',
                'title' => 'Dress Code Arbitrale',
                'content' => 'Gli arbitri dovranno presentarsi in divisa ufficiale FIG (polo bianca con logo FIG, pantaloni blu scuro, scarpe da golf). Durante il briefing è richiesto abbigliamento business casual.',
                'applies_to' => 'referee',
                'is_active' => true,
                'sort_order' => 40,
            ],
            [
                'code' => 'altro_orari_presenza',
                'category' => 'altro',
                'title' => 'Orari di Presenza',
                'content' => 'Gli arbitri dovranno presentarsi al circolo almeno 60 minuti prima dell\'orario di inizio previsto per la prima partenza. Al termine delle operazioni giornaliere, si richiede la permanenza fino alla chiusura dello scoring e alla firma dei cartellini.',
                'applies_to' => 'referee',
                'is_active' => true,
                'sort_order' => 41,
            ],
            [
                'code' => 'altro_materiale_fornito',
                'category' => 'altro',
                'title' => 'Materiale Fornito dal Circolo',
                'content' => 'Il circolo fornirà agli arbitri tutto il materiale necessario per lo svolgimento delle funzioni: regolamento locale, scorecards, matite, ombrelli, acqua minerale e eventuale materiale di cancelleria necessario.',
                'applies_to' => 'club',
                'is_active' => true,
                'sort_order' => 42,
            ],
        ];

        DB::beginTransaction();
        try {
            foreach ($clauses as $clause) {
                NotificationClause::create($clause);
            }

            DB::commit();

            $this->command->info('✓ Create '.count($clauses).' notification clauses');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('✗ Error creating clauses: '.$e->getMessage());
        }
    }
}
