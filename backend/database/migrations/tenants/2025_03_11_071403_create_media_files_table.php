<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->morphs('mediable');
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('restrict');
            $table->string('collection_name')
                ->comment('Logical grouping of media (e.g., lesson-audio, exercise-images)');
            $table->string('file_name');
            $table->string('mime_type');
            $table->string('disk')
                ->comment('Storage disk (local, s3, etc.)');
            $table->string('path')
                ->comment('Path to file in storage');
            $table->string('cdn_url')->nullable()
                ->comment('CDN URL for the file');
            $table->unsignedBigInteger('size')
                ->comment('File size in bytes');
            $table->json('conversions')->nullable()
                ->comment('Generated versions/conversions of the file');
            $table->json('responsive_images')->nullable()
                ->comment('Responsive image sizes');
            $table->json('custom_properties')->nullable()
                ->comment('Additional media properties');
            $table->json('generated_conversions')->nullable()
                ->comment('Status of pending/completed conversions');
            $table->json('metadata')->nullable()
                ->comment('File metadata (dimensions, duration, etc.)');
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Add indexes for efficient queries
            $table->index('collection_name');
            $table->index('disk');
            $table->index('order');
            $table->index(['mediable_type', 'mediable_id', 'collection_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};