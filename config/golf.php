<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sistema Golf Arbitri - Configurazioni
    |--------------------------------------------------------------------------
    */

    'emails' => [
        // Email istituzionali della Federazione
        'ufficio_campionati' => env('GOLF_EMAIL_CAMPIONATI', 'campionati@federgolf.it'),
        'arbitri' => env('GOLF_EMAIL_ARBITRI', 'arbitri@federgolf.it'),
        'info' => env('GOLF_EMAIL_INFO', 'info@federgolf.it'),
        'crc' => env('GOLF_EMAIL_CRC', 'crc@federgolf.it'),

        // Email per notifiche sistema
        'system_notifications' => env('GOLF_EMAIL_SYSTEM', 'system@federgolf.it'),
        'backup_notifications' => env('GOLF_EMAIL_BACKUP', 'backup@federgolf.it'),
    ],

    'backup' => [
        'retention_days' => env('GOLF_BACKUP_RETENTION', 30),
        'compress' => env('GOLF_BACKUP_COMPRESS', true),
        'encrypt' => env('GOLF_BACKUP_ENCRYPT', false),
        'destination' => env('GOLF_BACKUP_DESTINATION', 'local'),
        'max_size_mb' => env('GOLF_BACKUP_MAX_SIZE', 500),
        'notification_email' => env('GOLF_BACKUP_EMAIL'),
        'encryption_key' => env('GOLF_BACKUP_KEY', env('APP_KEY')),
    ],

    'zones' => [
        // Configurazioni per zone (SZR1-SZR7)
        'default_email_pattern' => 'szr{zone_id}@federgolf.it',
        'admin_email_pattern' => '{zone_code}@federgolf.it',

        // Mapping ID zona -> Codice cartella
        'folder_mapping' => [
            1 => 'SZR1',
            2 => 'SZR2',
            3 => 'SZR3',
            4 => 'SZR4',
            5 => 'SZR5',
            6 => 'SZR6',
            7 => 'SZR7',
        ],

        // Codice speciale per tornei nazionali
        'national_folder_code' => 'CRC',
    ],

    'referee_levels' => [
        // Livelli arbitri (valori normalizzati per DB)
        'values' => [
            'aspirante' => 'Aspirante',
            '1_livello' => 'Primo Livello',
            'regionale' => 'Regionale',
            'nazionale' => 'Nazionale',
            'internazionale' => 'Internazionale',
            'archivio' => 'Archivio',
        ],

        // Livelli che hanno accesso ai tornei nazionali
        'national_access' => ['Nazionale', 'Internazionale'],

        // Ordine gerarchico (per confronti)
        'hierarchy' => [
            'aspirante' => 0,
            '1_livello' => 1,
            'regionale' => 2,
            'nazionale' => 3,
            'internazionale' => 4,
        ],
    ],

    'user_types' => [
        // Tipi di utente del sistema
        'super_admin' => 'Super Amministratore',
        'national_admin' => 'Amministratore Nazionale',
        'admin' => 'Amministratore Zonale',
        'referee' => 'Arbitro',
    ],

    'tournament_statuses' => [
        // Stati possibili per i tornei
        'draft' => 'Bozza',
        'open' => 'Aperto',
        'closed' => 'Chiuso',
        'assigned' => 'Assegnato',
        'completed' => 'Completato',
        'cancelled' => 'Annullato',
    ],

    'assignment_roles' => [
        // Ruoli possibili nelle assegnazioni
        'default' => 'Arbitro',
        'director' => 'Direttore di Torneo',
        'referee' => 'Arbitro',
        'assistant' => 'Assistente',
        'observer' => 'Osservatore',
    ],

    'notifications' => [
        // Configurazioni notifiche torneo
        'default_sender' => env('GOLF_NOTIFICATION_FROM', 'noreply@federgolf.it'),
        'institutional_recipients' => [
            'always_include' => [
                'campionati@federgolf.it',
            ],
        ],
    ],

    'documents' => [
        // Configurazioni generazione documenti
        'storage_path' => 'convocazioni',
        'templates_path' => 'templates',
    ],
];
