<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->onDelete('set null');
            $table->string('action')
                ->comment('Type of action performed (e.g., create, update, delete, login)');
            $table->string('area')
                ->comment('System area where action was performed (e.g., content, users, settings)');
            $table->morphs('auditable');
            $table->json('old_values')->nullable()
                ->comment('Previous state of the modified resource');
            $table->json('new_values')->nullable()
                ->comment('New state of the modified resource');
            $table->json('metadata')->nullable()
                ->comment('Additional contextual information');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('is_system_action')->default(false)
                ->comment('Whether the action was performed by the system');
            $table->string('status')
                ->comment('Outcome of the action (success, failure, error)');
            $table->text('status_message')->nullable();
            $table->timestamp('performed_at');
            $table->timestamps();

            // Add indexes for efficient queries
            $table->index('action');
            $table->index('area');
            $table->index('performed_at');
            // Removed duplicate index: $table->index(['auditable_type', 'auditable_id']);
            $table->index('status');
            $table->index('ip_address');
            $table->index('is_system_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};