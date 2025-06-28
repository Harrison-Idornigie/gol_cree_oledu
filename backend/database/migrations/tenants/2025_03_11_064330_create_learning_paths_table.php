<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->string('title');
            $table->foreignId('language_id')
                ->nullable()
                ->constrained()
                ->onDelete('set null')
                ->comment('Legacy: Single language reference - use language_pair_id instead');

            $table->foreignId('language_pair_id')
                ->nullable()
                ->constrained('language_pairs')
                ->onDelete('set null')
                ->comment('Language pair for this learning path (source -> target)');

            $table->text('description');
            $table->string('target_level');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('User who created this learning path');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'language_pair_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_paths');
    }
};
