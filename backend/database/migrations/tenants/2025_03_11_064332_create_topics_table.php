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
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');

            // Topic details
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();

            // Organization
            $table->integer('order')->default(0);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->integer('xp_reward')->default(10);

                                                         // Gamification
            $table->integer('max_level')->default(5);    // Duolingo-style crown levels
            $table->boolean('is_bonus')->default(false); // Special/bonus topics

            // Metadata
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
