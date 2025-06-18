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
        Schema::create('user_analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('language_id')->nullable();
            $table->string('metric_type', 50); // learning_time, exercise_completion, word_mastery, etc.
            $table->json('metric_value'); // Flexible JSON storage for different metric types
            $table->string('context', 100)->nullable(); // lesson_id, exercise_id, etc.
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('language_id')->references('id')->on('languages')->onDelete('cascade');
            
            $table->index(['user_id', 'metric_type', 'recorded_at']);
            $table->index(['language_id', 'metric_type', 'recorded_at']);
            $table->index(['metric_type', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_analytics');
    }
};
