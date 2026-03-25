<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX C-3: Aggiunge i vincoli FK mancanti su notification_clause_selections.
 *
 * La migrazione originale (2025_08_29_000005) usava foreignId() senza ->constrained(),
 * lasciando le FK come semplici interi senza enforcement da parte del DB.
 * Eliminare una TournamentNotification lasciava record orfani in clause_selections.
 *
 * Questa migrazione aggiunge:
 *  - FK tournament_notification_id → tournament_notifications.id (ON DELETE CASCADE)
 *  - FK clause_id                  → notification_clauses.id      (ON DELETE CASCADE)
 *
 * Prima di aggiungere i vincoli, elimina i record orfani esistenti.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rimuove record orfani prima di aggiungere i vincoli (evita FK constraint violation)
        \Illuminate\Support\Facades\DB::statement(
            'DELETE FROM notification_clause_selections
             WHERE tournament_notification_id NOT IN (SELECT id FROM tournament_notifications)'
        );

        \Illuminate\Support\Facades\DB::statement(
            'DELETE FROM notification_clause_selections
             WHERE clause_id NOT IN (SELECT id FROM notification_clauses)'
        );

        Schema::table('notification_clause_selections', function (Blueprint $table) {
            // Aggiunge l'indice necessario per la FK prima di creare il vincolo
            // (MySQL lo richiede se non esiste già)
            $table->foreign('tournament_notification_id', 'fk_ncs_tournament_notification')
                ->references('id')
                ->on('tournament_notifications')
                ->onDelete('cascade');

            $table->foreign('clause_id', 'fk_ncs_clause')
                ->references('id')
                ->on('notification_clauses')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('notification_clause_selections', function (Blueprint $table) {
            $table->dropForeign('fk_ncs_tournament_notification');
            $table->dropForeign('fk_ncs_clause');
        });
    }
};
