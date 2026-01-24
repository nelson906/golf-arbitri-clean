<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge campo per notifiche gare nazionali:
 * - notification_type: distingue CRC (crc_referees) da ZONA (zone_observers)
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('tournament_notifications')) {
            return;
        }

        Schema::table('tournament_notifications', function (Blueprint $table) {
            // Tipo notifica: null per gare zonali (retrocompatibilità),
            // 'crc_referees' per CRC, 'zone_observers' per ZONA
            if (! Schema::hasColumn('tournament_notifications', 'notification_type')) {
                $column = $table->string('notification_type', 50)->nullable();
                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    $column->after('tournament_id');
                }
            }
        });

        // Aggiungi indice per query efficienti (dopo aver creato le colonne)
        $driver = Schema::getConnection()->getDriverName();
        try {
            if ($driver === 'sqlite') {
                DB::statement('CREATE INDEX IF NOT EXISTS tn_tournament_type_index ON tournament_notifications (tournament_id, notification_type)');
            } elseif ($driver === 'pgsql') {
                DB::statement('CREATE INDEX IF NOT EXISTS tn_tournament_type_index ON tournament_notifications (tournament_id, notification_type)');
            } else {
                DB::statement('CREATE INDEX tn_tournament_type_index ON tournament_notifications (tournament_id, notification_type)');
            }
        } catch (Throwable $e) {
            // Ignore if already exists or not supported by driver
        }
    }

    public function down()
    {
        if (! Schema::hasTable('tournament_notifications')) {
            return;
        }

        Schema::table('tournament_notifications', function (Blueprint $table) {
            // Rimuovi indice
            try {
                $driver = Schema::getConnection()->getDriverName();
                if ($driver === 'sqlite') {
                    DB::statement('DROP INDEX IF EXISTS tn_tournament_type_index');
                } elseif ($driver === 'pgsql') {
                    DB::statement('DROP INDEX IF EXISTS tn_tournament_type_index');
                } else {
                    DB::statement('DROP INDEX tn_tournament_type_index ON tournament_notifications');
                }
            } catch (Throwable $e) {
                // Ignore if missing or not supported by driver
            }

            // Rimuovi colonna
            if (Schema::hasColumn('tournament_notifications', 'notification_type')) {
                $table->dropColumn('notification_type');
            }
        });
    }
};
