<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('title');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->text('description');
            $table->integer('order');
            $table->timestamps();

            // Add index for ordering
            $table->index(['learning_path_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};