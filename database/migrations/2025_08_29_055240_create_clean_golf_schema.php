<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_national')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_national']);
            $table->index('code');
        });

        Schema::create('tournament_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 20)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_national')->default(false);
            $table->enum('level', ['zonale', 'nazionale'])->default('zonale');
            $table->enum('required_level', ['aspirante', '1_livello', 'regionale', 'nazionale', 'internazionale'])->default('aspirante');
            $table->text('calendar_color')->nullable();
            $table->integer('min_referees')->default(1);
            $table->integer('max_referees')->default(2);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['is_national', 'is_active']);
        });

        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province', 2)->nullable();
            $table->foreignId('zone_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['zone_id', 'is_active']);
            $table->index('city');
        });

        // Drop e ricrea users per avere la versione unificata
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->enum('user_type', ['super_admin', 'national_admin', 'admin', 'referee'])->default('referee');
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();

            $table->string('referee_code')->nullable();
            $table->enum('level', ['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale', 'Archivio'])->default('1_livello');
            $table->enum('gender', ['male', 'female', 'mixed'])->default('mixed');
            $table->date('certified_date')->nullable();
            $table->foreignId('zone_id')->nullable()->constrained()->onDelete('set null');

            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('tax_code', 16)->nullable();
            $table->string('badge_number')->nullable();
            $table->date('first_certification_date')->nullable();
            $table->date('last_renewal_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('bio')->nullable();
            $table->integer('experience_years')->default(0);

            $table->json('qualifications')->nullable();
            $table->json('languages')->nullable();
            $table->json('specializations')->nullable();
            $table->json('preferences')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('available_for_international')->default(false);
            $table->integer('total_tournaments')->default(0);
            $table->integer('tournaments_current_year')->default(0);

            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('profile_completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_type', 'zone_id']);
            $table->index(['zone_id', 'is_active']);
            $table->index(['level', 'is_active']);
            $table->index('gender');
        });

        Schema::create('referee_career_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->json('tournaments_by_year')->nullable();
            $table->json('assignments_by_year')->nullable();
            $table->json('availabilities_by_year')->nullable();
            $table->json('level_changes_by_year')->nullable();
            $table->json('career_stats')->nullable();

            $table->year('last_updated_year')->default(2025);
            $table->decimal('data_completeness_score', 3, 2)->default(0.00);
            $table->timestamps();

            $table->index(['user_id', 'last_updated_year']);
            $table->index('data_completeness_score');
        });

        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->datetime('availability_deadline');

            $table->foreignId('club_id')->constrained();
            $table->foreignId('tournament_type_id')->constrained('tournament_types');
            $table->foreignId('zone_id')->nullable()->constrained()->onDelete('cascade');

            $table->enum('status', ['draft', 'open', 'closed', 'assigned', 'completed', 'cancelled'])->default('draft');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['zone_id', 'status']);
            $table->index(['start_date', 'status']);
            $table->index(['tournament_type_id', 'status']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['Direttore di Torneo', 'Arbitro', 'Osservatore']);
            $table->string('status', 50)->default('assigned');
            $table->boolean('is_confirmed')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->unique(['user_id', 'tournament_id']);
            $table->index(['tournament_id', 'role']);
            $table->index(['user_id', 'assigned_at']);
        });

        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['user_id', 'tournament_id']);
            $table->index(['tournament_id', 'submitted_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('recipient_type', ['referee', 'club', 'institutional']);
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('template_used')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed', 'cancelled'])->default('pending');
            $table->timestamp(column: 'sent_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('priority')->default(0);
            $table->foreignId('sender_id')->nullable()->constrained('users');
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['assignment_id', 'recipient_type']);
            $table->index(['status', 'created_at']);
            $table->index(['recipient_email', 'status']);
            $table->index(['tournament_id', 'recipient_type']);
            $table->index(['status', 'sent_at']);

            // $table->index(['status', 'priority', 'created_at'], 'idx_notifications_queue');
            // $table->index(['recipient_type', 'created_at'], 'idx_notifications_type_date');
        });

        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->string('name');
            $table->enum('type', ['referee', 'club', 'institutional', 'other']);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('institutional_emails', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('category'); // FIG, Regionale, etc.
            $table->boolean('is_global')->default(false); // Per tutte le zone o specifica
            $table->foreignId('zone_id')->nullable()->constrained();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            // Informazioni documento
            $table->string('name')->comment('Nome visualizzato');
            $table->string('original_name')->comment('Nome file originale');

            // Storage
            $table->string('file_path')->comment('Path nel storage');
            $table->bigInteger('file_size')->comment('Dimensione in bytes');
            $table->string('mime_type')->comment('Tipo MIME del file');

            // Classificazione
            $table->enum('category', ['general', 'tournament', 'regulation', 'form', 'template'])
                ->default('general')
                ->comment('Categoria del documento');

            $table->enum('type', ['pdf', 'document', 'spreadsheet', 'image', 'text', 'other'])
                ->default('other')
                ->comment('Tipo di file dedotto dal MIME');

            // Metadati
            $table->text('description')
                ->nullable()
                ->comment('Descrizione opzionale');

            // Relazioni
            $table->foreignId('tournament_id')
                ->nullable()
                ->constrained('tournaments')
                ->onDelete('cascade')
                ->comment('Torneo associato (opzionale)');

            $table->foreignId('zone_id')
                ->nullable()
                ->constrained('zones')
                ->onDelete('cascade')
                ->comment('Zona di appartenenza (null = globale)');

            $table->foreignId('uploader_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Utente che ha caricato il file');

            // Permissions e stats
            $table->boolean('is_public')
                ->default(false)
                ->comment('Visibile a tutti gli utenti');

            $table->integer('download_count')
                ->default(0)
                ->comment('Numero di download');

            $table->timestamps();

            // Indici per performance
            $table->index(['category', 'type'], 'idx_documents_category_type');
            $table->index(['zone_id', 'is_public'], 'idx_documents_zone_public');
            $table->index('uploader_id', 'idx_documents_uploader');
            $table->index('tournament_id', 'idx_documents_tournament');
            $table->index(['created_at', 'category'], 'idx_documents_date_category');
            $table->index('file_size', 'idx_documents_size');
        });

        Schema::create('communications', function (Blueprint $table) {
            $table->id();

            // Contenuto principale
            $table->string('title');
            $table->text('content');

            // Tipologia e classificazione
            $table->enum('type', ['announcement', 'alert', 'maintenance', 'info'])
                ->default('info')
                ->comment('Tipo di comunicazione');

            $table->enum('status', ['draft', 'published', 'expired'])
                ->default('draft')
                ->comment('Stato della comunicazione');

            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                ->default('normal')
                ->comment('Priorità della comunicazione');

            // Relazioni
            $table->foreignId('zone_id')
                ->nullable()
                ->constrained('zones')
                ->onDelete('cascade')
                ->comment('Zona specifica (null = globale)');

            $table->foreignId('author_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Autore della comunicazione');

            // Programmazione temporale
            $table->timestamp('scheduled_at')
                ->nullable()
                ->comment('Quando pubblicare (null = subito)');

            $table->timestamp('expires_at')
                ->nullable()
                ->comment('Quando far scadere (null = mai)');

            $table->timestamp('published_at')
                ->nullable()
                ->comment('Quando è stata effettivamente pubblicata');

            $table->timestamps();

            // Indici per performance
            $table->index(['status', 'type'], 'idx_communications_status_type');
            $table->index(['zone_id', 'status'], 'idx_communications_zone_status');
            $table->index('scheduled_at', 'idx_communications_scheduled');
            $table->index(['expires_at', 'status'], 'idx_communications_expires_status');
            $table->index('author_id', 'idx_communications_author');
        });

        Schema::create('tournament_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['sent', 'partial', 'failed', 'pending'])->default('pending');
            $table->integer('total_recipients')->default(0);
            $table->text('referee_list')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('details')->nullable();
            $table->json('templates_used')->nullable();
            $table->text('error_message')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'status']);
            $table->index(['sent_at']);
            $table->index(['status']);
        });


        $this->seedBasicData();
    }

    public function down(): void
    {
        Schema::dropIfExists('availabilities');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('tournaments');
        Schema::dropIfExists('referee_career_history');
        Schema::dropIfExists('users');
        Schema::dropIfExists('clubs');
        Schema::dropIfExists('tournament_types');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('institutional_emails');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('communications');
        Schema::dropIfExists('tournament_notifications');
    }

    private function seedBasicData(): void
    {
        DB::table('zones')->insert([
            ['code' => 'SZR1', 'name' => 'Sezione Zonale Regole 1', 'description' => 'Piemonte-Valle d\'Aosta-Liguria', 'is_national' => false],
            ['code' => 'SZR2', 'name' => 'Sezione Zonale Regole 2', 'description' => 'Lombardia', 'is_national' => false],
            ['code' => 'SZR3', 'name' => 'Sezione Zonale Regole 3', 'description' => 'Veneto-Trentino-Friuli', 'is_national' => false],
            ['code' => 'SZR4', 'name' => 'Sezione Zonale Regole 4', 'description' => 'Emilia-Romagna', 'is_national' => false],
            ['code' => 'SZR5', 'name' => 'Sezione Zonale Regole 5', 'description' => 'Toscana-Umbria', 'is_national' => false],
            ['code' => 'SZR6', 'name' => 'Sezione Zonale Regole 6', 'description' => 'Lazio-Abruzzo-Molise', 'is_national' => false],
            ['code' => 'SZR7', 'name' => 'Sezione Zonale Regole 7', 'description' => 'Sud Italia-Sicilia-Sardegna', 'is_national' => false],
            ['code' => 'CRC', 'name' => 'Comitato Regole Campionati', 'description' => 'Comitato Regole e Campionati', 'is_national' => true],
        ]);

        DB::table('institutional_emails')->insert([
            // FIG
            ['name' => 'Federazione Italiana Golf - Segreteria', 'email' => 'segreteria@federgolf.it', 'category' => 'FIG', 'is_global' => true, 'zone_id' => null, 'is_active' => true],
            ['name' => 'FIG - Direzione Tecnica', 'email' => 'tecnica@federgolf.it', 'category' => 'FIG', 'is_global' => true, 'zone_id' => null, 'is_active' => true],
            ['name' => 'FIG - Comitato Regole', 'email' => 'regole@federgolf.it', 'category' => 'FIG', 'is_global' => true, 'zone_id' => null, 'is_active' => true],

            // Regionali (esempi)
            ['name' => 'Comitato Regionale Lombardia', 'email' => 'lombardia@federgolf.it', 'category' => 'Regionale', 'is_global' => false, 'zone_id' => 1, 'is_active' => true],
            ['name' => 'Comitato Regionale Lazio', 'email' => 'lazio@federgolf.it', 'category' => 'Regionale', 'is_global' => false, 'zone_id' => 2, 'is_active' => true],

            // Altri enti
            ['name' => 'European Golf Association', 'email' => 'info@ega-golf.ch', 'category' => 'Internazionale', 'is_global' => true, 'zone_id' => null, 'is_active' => true],
        ]);
    }
};
