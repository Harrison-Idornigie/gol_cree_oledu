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
        // Word management
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('language_id')->constrained();
            $table->string('text');
            $table->string('pronunciation_key')->nullable(); // IPA or similar
            $table->string('part_of_speech')->nullable();
            $table->json('metadata')->nullable(); // Additional word properties
            $table->timestamps();
            $table->enum('status', ['draft', 'published', 'archived'])
                ->default('draft')
                ->comment('Publication status of the word');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('User who created this word');
            $table->unique(['language_id', 'text', 'part_of_speech']);
            $table->index(['tenant_id', 'language_id']);
        });

        Schema::create('word_translations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('word_id')->constrained()->onDelete('cascade');
            $table->foreignId('language_id')->constrained(); // Target language
            $table->string('text');
            $table->string('pronunciation_key')->nullable();
            $table->text('context_notes')->nullable();
            $table->json('usage_examples')->nullable();
            $table->integer('translation_order')->default(1); // For multiple meanings
            $table->timestamps();

            $table->index(['word_id', 'language_id', 'translation_order']);
        });

        // Exception words for untranslatable content
        Schema::create('exception_words', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('language_id')->constrained();
            $table->string('text');
            $table->enum('type', ['proper_noun', 'technical_term', 'borrowed_word', 'number', 'date', 'custom']);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional properties like origin language, category
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['language_id', 'text', 'type']);
            $table->index(['tenant_id', 'language_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_translations');
        Schema::dropIfExists('words');
        Schema::dropIfExists('exception_words');
    }
};