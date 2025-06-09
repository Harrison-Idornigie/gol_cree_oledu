<?php

use App\Models\Exercise;
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
        // Add the new exercise type constant to the Exercise model
        // This is done in the model file, not in the migration
        
        // Create a table to track picture exercise attempts
        Schema::create('picture_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->integer('selected_option');
            $table->boolean('is_correct');
            $table->integer('attempt_number');
            $table->timestamps();
            
            $table->index(['user_id', 'exercise_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('picture_attempts');
    }
};
