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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
