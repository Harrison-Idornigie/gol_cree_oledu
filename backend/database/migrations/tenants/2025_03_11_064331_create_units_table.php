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
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('learning_path_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->comment('Reference to content template used to create this unit');
            $table->string('title');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->text('description')->nullable();
            $table->integer('order');
            $table->timestamps();

            // Add index for ordering
            $table->index(['learning_path_id', 'order']);
            $table->index(['tenant_id', 'status']);
            $table->index(['template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
