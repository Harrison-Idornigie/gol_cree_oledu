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
        
        // If needed, you can add any additional columns or tables specific to conversation exercises
        // For example, if you want to track conversation progress separately
        Schema::create('conversation_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->integer('last_step_completed')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            
            $table->unique(['user_id', 'exercise_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_progress');
    }
};