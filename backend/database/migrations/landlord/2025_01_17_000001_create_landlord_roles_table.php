<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create landlord roles and user_roles tables
 * 
 * This migration creates the roles and user_roles tables in the central/landlord 
 * database for system-wide role-based access control.
 * 
 * This is separate from tenant-specific roles which exist in individual
 * tenant databases.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create roles table
        Schema::create('central_roles', function (Blueprint $table) {
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

        // Create central_user_roles pivot table
        Schema::create('central_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('central_users')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('central_roles')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint to prevent duplicate role assignments
            $table->unique(['user_id', 'role_id']);

            // Indexes
            $table->index('user_id');
            $table->index('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('central_user_roles');
        Schema::dropIfExists('central_roles');
    }
};
