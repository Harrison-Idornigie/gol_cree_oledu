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
            $table->foreignId('unit_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('topic');
            $table->text('content');
            $table->timestamps();

            // Add indexes for common queries
            $table->index('unit_id');
            $table->index('topic');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_book_entries');
    }
};