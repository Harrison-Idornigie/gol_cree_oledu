<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocabulary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('word');
            $table->string('translation');
            $table->text('example')
                ->nullable();
            $table->timestamps();

            // Add indexes for common queries
            $table->index('lesson_id');
            $table->index('word');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vocabulary_items');
    }
};