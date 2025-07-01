<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            $table->morphs('trackable');
            $table->enum('status', ['not_started', 'learning', 'practicing', 'mastered', 'completed', 'in_progress'])
                ->default('not_started');
            $table->integer('strength')->default(0)->comment('Word strength 0-5 (Duolingo-style)');
            $table->integer('streak')->default(0)->comment('Consecutive correct answers');
            $table->integer('mistake_count')->default(0)->comment('Number of mistakes made');
            $table->timestamp('last_practiced_at')->nullable()->comment('When word was last practiced');
            $table->json('meta_data')
                ->nullable()
                ->comment('Additional progress data like scores, exercise results');
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            // Add indexes for common queries
            $table->index(['user_id', 'trackable_type', 'trackable_id']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'strength']);
            $table->index('last_practiced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
};
