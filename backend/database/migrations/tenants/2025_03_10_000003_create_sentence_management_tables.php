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
        // Sentence management
        Schema::create('sentences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained();
            $table->text('text');
            $table->string('pronunciation_key')->nullable();
            $table->json('metadata')->nullable(); // Difficulty level, tags, etc.
            $table->timestamps();
        });

        Schema::create('sentence_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sentence_id')->constrained()->onDelete('cascade');
            $table->foreignId('language_id')->constrained(); // Target language
            $table->text('text');
            $table->string('pronunciation_key')->nullable();
            $table->text('context_notes')->nullable();
            $table->timestamps();

            $table->unique(['sentence_id', 'language_id']);
        });

        // Word-sentence relationships with timing
        Schema::create('sentence_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sentence_id')->constrained()->onDelete('cascade');
            $table->foreignId('word_id')->constrained();
            $table->integer('position'); // Word order in sentence
            $table->float('start_time')->nullable(); // Start time in audio (seconds)
            $table->float('end_time')->nullable();   // End time in audio (seconds)
            $table->json('metadata')->nullable(); // Any additional timing/display info
            $table->timestamps();

            $table->unique(['sentence_id', 'position']);
            $table->index(['sentence_id', 'word_id']);
        });

        // Usage examples
        Schema::create('usage_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('word_id')->constrained()->onDelete('cascade');
            $table->foreignId('sentence_id')->constrained();
            $table->string('type'); // common usage, idiom, formal, casual, etc.
            $table->integer('difficulty_level');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['word_id', 'type', 'difficulty_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_examples');
        Schema::dropIfExists('sentence_words');
        Schema::dropIfExists('sentence_translations');
        Schema::dropIfExists('sentences');
    }
};