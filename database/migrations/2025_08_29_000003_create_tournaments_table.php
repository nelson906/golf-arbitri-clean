<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->dateTime('availability_deadline');
            $table->foreignId('club_id')->constrained('clubs');
            $table->foreignId('tournament_type_id')->constrained('tournament_types');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->onDelete('cascade');
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
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');
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
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['user_id', 'tournament_id']);
            $table->index(['tournament_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availabilities');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('tournaments');
    }
};
