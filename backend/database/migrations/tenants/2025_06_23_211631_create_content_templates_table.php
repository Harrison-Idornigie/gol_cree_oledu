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
        Schema::create('content_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');

            // Template identification
            $table->enum('template_type', ['unit', 'topic', 'lesson', 'exercise'])->comment('Type of content this template creates');
            $table->string('name');
            $table->text('description')->nullable();

            // Template structure and content
            $table->json('template_data')->comment('Template structure and patterns');
            $table->integer('difficulty_level')->default(1)->comment('Difficulty level 1-10');
            $table->string('skill_focus')->nullable()->comment('Primary skill focus: vocabulary, grammar, listening, etc.');

            // Exercise and content requirements
            $table->json('exercise_types')->nullable()->comment('Supported exercise types for this template');
            $table->json('vocabulary_requirements')->nullable()->comment('Vocabulary requirements and constraints');

            // Template metadata
            $table->foreignId('created_by')->constrained('users')->comment('Template creator');
            $table->integer('usage_count')->default(0)->comment('Number of times template was used');

            $table->timestamps();

            // Indexes for performance
            $table->index(['tenant_id', 'template_type']);
            $table->index(['skill_focus', 'difficulty_level']);
            $table->index(['created_by']);
            $table->index(['usage_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_templates');
    }
};
