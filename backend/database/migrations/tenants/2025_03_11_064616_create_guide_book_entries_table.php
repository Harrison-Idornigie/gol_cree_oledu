<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_book_entries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->text('description')->nullable();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->onDelete('cascade');
            $table->foreignId('unit_id')->nullable()->constrained('units')->onDelete('cascade');
            $table->foreignId('language_id')->nullable()->constrained('languages')->onDelete('cascade');
            $table->json('words_introduced')->nullable()->comment('New words introduced in this lesson');
            $table->json('words_reused')->nullable()->comment('Words from previous lessons being reused');
            $table->integer('word_count')->default(0)->comment('Total number of words in this lesson');
            $table->integer('difficulty_level')->nullable();
            $table->json('tags')->nullable();
            $table->json('references')->nullable();
            $table->integer('order')->nullable();
            $table->enum('category', [
                'lesson_guide',
                'grammar',
                'pronunciation',
                'culture',
                'conversation',
                'reference'
            ])->default('lesson_guide');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Add indexes for common queries
            $table->index(['status', 'category']);
            $table->index(['lesson_id', 'order']);
            $table->index(['unit_id', 'order']);
            $table->index('language_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_book_entries');
    }
};
