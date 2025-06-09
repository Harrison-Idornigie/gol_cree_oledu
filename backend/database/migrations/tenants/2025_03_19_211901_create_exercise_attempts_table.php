<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('is_correct');
            $table->json('user_answer');
            $table->integer('time_taken_seconds')->nullable();
            $table->float('score')->nullable();
            $table->boolean('passed')->nullable();
            $table->json('feedback')->nullable();
            $table->integer('attempt_number')->default(1);
            $table->timestamps();

            $table->index(['exercise_id', 'is_correct']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_attempts');
    }
};