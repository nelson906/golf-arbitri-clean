<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_national')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_national']);
            $table->index('code');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->enum('user_type', ['super_admin', 'national_admin', 'admin', 'referee'])->default('referee');
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            // Referee-specific fields
            $table->string('referee_code')->nullable();
            $table->enum('level', ['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale', 'Archivio'])->default('1_livello');
            $table->enum('gender', ['male', 'female', 'mixed'])->default('mixed');
            $table->date('certified_date')->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->onDelete('set null');
            $table->string('club_member')->nullable();

            // Contact info
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('tax_code', 16)->nullable();
            $table->string('badge_number')->nullable();
            $table->date('first_certification_date')->nullable();
            $table->date('last_renewal_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('bio')->nullable();
            $table->integer('experience_years')->default(0);

            // JSON fields
            $table->json('qualifications')->nullable();
            $table->json('languages')->nullable();
            $table->json('specializations')->nullable();
            $table->json('preferences')->nullable();

            // Status and stats
            $table->boolean('is_active')->default(true);
            $table->boolean('available_for_international')->default(false);
            $table->integer('total_tournaments')->default(0);
            $table->integer('tournaments_current_year')->default(0);

            // Timestamps
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('profile_completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_type', 'zone_id']);
            $table->index(['zone_id', 'is_active']);
            $table->index(['level', 'is_active']);
            $table->index('gender');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('zones');
    }
};
