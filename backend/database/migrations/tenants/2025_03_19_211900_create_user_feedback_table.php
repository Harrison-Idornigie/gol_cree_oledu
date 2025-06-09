<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->morphs('content');
            $table->text('message');
            $table->integer('rating')->nullable();
            $table->timestamps();

            // The morphs() method already creates indexes for content_type and content_id
            // $table->index(['content_type', 'content_id']);
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feedback');
    }
};