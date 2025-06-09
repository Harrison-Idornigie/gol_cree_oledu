<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            $table->json('metadata')->nullable()
                ->comment('Additional role assignment data (e.g., expiry, restrictions)');
            $table->timestamps();

            // Create composite primary key
            $table->primary(['role_id', 'user_id']);

            // Add indexes for efficient queries
            $table->index('user_id');
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};