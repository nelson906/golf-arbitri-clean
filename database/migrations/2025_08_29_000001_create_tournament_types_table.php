<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 20)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_national')->default(false);
            $table->enum('level', ['zonale', 'nazionale'])->default('zonale');
            $table->enum('required_level', ['aspirante', '1_livello', 'regionale', 'nazionale', 'internazionale'])->default('aspirante');
            $table->text('calendar_color')->nullable();
            $table->integer('min_referees')->default(1);
            $table->integer('max_referees')->default(2);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['is_national', 'is_active']);
        });

        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province', 2)->nullable();
            $table->foreignId('zone_id')->constrained('zones')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['zone_id', 'is_active']);
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clubs');
        Schema::dropIfExists('tournament_types');
    }
};
