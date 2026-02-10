<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referee_career_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
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
    }

    public function down(): void
    {
        Schema::dropIfExists('referee_career_history');
    }
};
