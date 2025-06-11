<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_user', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('membership_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            $table->json('metadata')->nullable()
                ->comment('Additional membership assignment data (e.g., expiry, restrictions)');
            $table->timestamps();

            // Create composite primary key
            $table->primary(['membership_id', 'user_id']);

            // Add indexes for efficient queries
            $table->index('user_id');
            $table->index('membership_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_user');
    }
};