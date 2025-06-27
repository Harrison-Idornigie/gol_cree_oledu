<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('topic_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->comment('Reference to content template used to create this lesson');
            $table->string('title');
            $table->text('description');
            $table->integer('order');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('User who created this lesson');
            $table->timestamps();

            // Add index for ordering
            $table->index(['topic_id', 'order']);
            $table->index(['tenant_id', 'status']);
            $table->index(['template_id']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
