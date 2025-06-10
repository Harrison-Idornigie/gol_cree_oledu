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
                ->onDelete('set null');

            $table->text('description');
            $table->string('target_level');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_paths');
    }
};