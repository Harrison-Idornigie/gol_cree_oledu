<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create landlord memberships and memberships tables
 * 
 * This migration creates the memberships and memberships tables in the central/landlord 
 * database for system-wide membership-based access control.
 * 
 * This is separate from tenant-specific memberships which exist in individual
 * tenant databases.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create memberships table
        Schema::create('central_memberships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->json('permissions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('is_system');
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('central_memberships');
    }
};
