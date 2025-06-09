<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_versions', function (Blueprint $table) {
            $table->id();
            $table->morphs('versionable');
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('restrict');
            $table->integer('version_number');
            $table->json('content')
                ->comment('Complete snapshot of the content at this version');
            $table->json('changes')
                ->comment('Specific changes made in this version');
            $table->string('change_type')
                ->comment('Type of change: create, update, delete, restore');
            $table->text('change_reason')->nullable();
            $table->boolean('is_major_version')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable()
                ->comment('Additional version information');
            $table->timestamps();

            // Add indexes for efficient queries with shorter names
            $table->index(['versionable_type', 'versionable_id', 'version_number'], 'cv_version_lookup_idx');
            $table->index('published_at');
            $table->index('created_at');
            
            // Ensure version numbers are unique per content item
            $table->unique(['versionable_type', 'versionable_id', 'version_number'], 'cv_unique_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_versions');
    }
};