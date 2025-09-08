<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
