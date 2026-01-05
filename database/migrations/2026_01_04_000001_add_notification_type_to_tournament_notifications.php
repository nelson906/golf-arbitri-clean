<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge campo per notifiche gare nazionali:
 * - notification_type: distingue CRC (crc_referees) da ZONA (zone_observers)
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('tournament_notifications', function (Blueprint $table) {
            // Tipo notifica: null per gare zonali (retrocompatibilità),
            // 'crc_referees' per CRC, 'zone_observers' per ZONA
            if (!Schema::hasColumn('tournament_notifications', 'notification_type')) {
                $table->string('notification_type', 50)->nullable()->after('tournament_id');
            }
        });

        // Aggiungi indice per query efficienti (dopo aver creato le colonne)
        Schema::table('tournament_notifications', function (Blueprint $table) {
            // Verifica se l'indice esiste già
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('tournament_notifications');
            if (!isset($indexes['tn_tournament_type_index'])) {
                $table->index(['tournament_id', 'notification_type'], 'tn_tournament_type_index');
            }
        });
    }

    public function down()
    {
        Schema::table('tournament_notifications', function (Blueprint $table) {
            // Rimuovi indice
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('tournament_notifications');
            if (isset($indexes['tn_tournament_type_index'])) {
                $table->dropIndex('tn_tournament_type_index');
            }

            // Rimuovi colonna
            if (Schema::hasColumn('tournament_notifications', 'notification_type')) {
                $table->dropColumn('notification_type');
            }
        });
    }
};
