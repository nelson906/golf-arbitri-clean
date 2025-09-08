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
        Schema::table('assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('assignments', 'status')) {
                $table->string('status', 50)->default('assigned')->after('role');
            }
            if (!Schema::hasColumn('assignments', 'is_confirmed')) {
                $table->boolean('is_confirmed')->default(false)->after('status');
            }
            if (!Schema::hasColumn('assignments', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('is_confirmed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'confirmed_at')) {
                $table->dropColumn('confirmed_at');
            }
            if (Schema::hasColumn('assignments', 'is_confirmed')) {
                $table->dropColumn('is_confirmed');
            }
            if (Schema::hasColumn('assignments', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
