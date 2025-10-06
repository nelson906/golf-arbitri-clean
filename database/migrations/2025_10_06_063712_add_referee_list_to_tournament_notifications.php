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
        Schema::table('tournament_notifications', function (Blueprint $table) {
            $table->text('referee_list')->nullable()->after('tournament_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_notifications', function (Blueprint $table) {
            $table->dropColumn('referee_list');
        });
    }
};
