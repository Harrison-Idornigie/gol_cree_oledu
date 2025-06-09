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
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            $table->morphs('trackable');
            $table->enum('status', ['not_started', 'in_progress', 'completed'])
                ->default('not_started');
            $table->json('meta_data')
                ->nullable()
                ->comment('Additional progress data like scores, exercise results');
            $table->timestamps();

            // Add indexes for common queries
            $table->index(['user_id', 'trackable_type', 'trackable_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
};