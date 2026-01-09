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
        Schema::table('institutional_emails', function (Blueprint $table) {
            // Aggiungi campo per ricevere tutte le notifiche
            $table->boolean('receive_all_notifications')->default(false)->after('is_active');

            // Aggiungi campo JSON per i tipi di notifica specifici
            $table->json('notification_types')->nullable()->after('receive_all_notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('institutional_emails', function (Blueprint $table) {
            $table->dropColumn(['receive_all_notifications', 'notification_types']);
        });
    }
};
