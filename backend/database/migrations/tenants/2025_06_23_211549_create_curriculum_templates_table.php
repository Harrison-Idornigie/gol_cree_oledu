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
        Schema::create('curriculum_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');

            // Template identification
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('language_pair_id')->constrained()->comment('Source-target language pair');
            $table->enum('proficiency_level', ['A1', 'A2', 'B1', 'B2', 'C1', 'C2']);

            // Template content and structure
            $table->json('template_data')->comment('Complete curriculum structure with units, topics, lessons');
            $table->json('prerequisites')->nullable()->comment('Required knowledge or completed templates');
            $table->integer('estimated_hours')->nullable()->comment('Estimated completion time in hours');

            // Template metadata
            $table->boolean('is_official')->default(false)->comment('Official template vs user-created');
            $table->foreignId('created_by')->constrained('users')->comment('Template creator');

            // Usage and effectiveness tracking
            $table->integer('usage_count')->default(0)->comment('Number of times template was instantiated');
            $table->decimal('effectiveness_score', 5, 2)->nullable()->comment('Calculated effectiveness score 0-100');

            $table->timestamps();

            // Indexes for performance
            $table->index(['tenant_id', 'proficiency_level']);
            $table->index(['language_pair_id', 'is_official']);
            $table->index(['effectiveness_score']);
            $table->index(['usage_count']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_templates');
    }
};
