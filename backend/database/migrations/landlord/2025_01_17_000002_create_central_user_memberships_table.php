<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Central User Memberships Pivot Table
 * 
 * This migration creates the pivot table that links central users
 * to their memberships/roles in the system.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('central_user_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('central_users')->onDelete('cascade');
            $table->foreignId('membership_id')->constrained('central_memberships')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint to prevent duplicate membership assignments
            $table->unique(['user_id', 'membership_id']);

            // Indexes
            $table->index('user_id');
            $table->index('membership_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('central_user_memberships');
    }
};
