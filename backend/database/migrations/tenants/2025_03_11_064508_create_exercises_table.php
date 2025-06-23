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
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->comment('Reference to content template used to create this exercise');

            // Exercise details
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type');                         // multiple_choice, fill_blank, matching, etc.
            $table->string('purpose')->default('practice'); // practice, checkpoint, review, assessment
            $table->json('content');                        // Exercise-specific content structure
            $table->json('answers')->nullable();            // Correct answers
            $table->json('metadata')->nullable();           // Additional exercise settings

            // Configuration
            $table->integer('time_limit')->nullable();   // In seconds, null for no limit
            $table->integer('max_attempts')->nullable(); // null for unlimited
            $table->boolean('show_feedback')->default(true);
            $table->boolean('show_hints')->default(true);
            $table->integer('xp_reward')->default(5);

            // Assessment configuration
            $table->integer('passing_score')->default(70);
            $table->boolean('requires_previous')->default(false);
            $table->boolean('show_solutions_after')->default(true);
            $table->integer('min_correct_required')->nullable();
            $table->boolean('is_checkpoint')->default(false);

            // Organization
            $table->string('difficulty_level')->default('beginner');
            $table->integer('order')->default(0);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_published')->default(false);

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['template_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
