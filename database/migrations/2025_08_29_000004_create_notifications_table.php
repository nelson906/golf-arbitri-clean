<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->onDelete('cascade');
            $table->foreignId('tournament_id')->nullable()->constrained('tournaments')->onDelete('cascade');
            $table->enum('recipient_type', ['referee', 'club', 'institutional']);
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('template_used')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('priority')->default(0);
            $table->foreignId('sender_id')->nullable()->constrained('users');
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['assignment_id', 'recipient_type']);
            $table->index(['status', 'created_at']);
            $table->index(['recipient_email', 'status']);
            $table->index(['tournament_id', 'recipient_type']);
            $table->index(['status', 'sent_at']);
        });

        Schema::create('tournament_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');
            $table->string('notification_type', 50)->nullable();
            $table->text('referee_list')->nullable();
            $table->json('recipients')->nullable();
            $table->json('content')->nullable();
            $table->json('metadata')->nullable();
            $table->json('documents')->nullable();
            $table->enum('status', ['sent', 'partial', 'failed', 'pending'])->default('pending');
            $table->boolean('is_prepared')->default(false);
            $table->string('workflow_status')->default('draft');
            $table->string('last_step_completed')->nullable();
            $table->json('workflow_data')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('configured_at')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('details')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'status']);
            $table->index('sent_at');
            $table->index('status');
            $table->index('workflow_status');
            $table->index(['tournament_id', 'workflow_status']);
            $table->index(['tournament_id', 'notification_type'], 'tn_tournament_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_notifications');
        Schema::dropIfExists('notifications');
    }
};
