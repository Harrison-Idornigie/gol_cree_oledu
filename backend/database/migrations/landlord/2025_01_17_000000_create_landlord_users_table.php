<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create landlord users table
 * 
 * This migration creates the users table in the central/landlord database
 * for system-wide users like super administrators who manage the entire
 * multi-tenant system.
 * 
 * This is separate from tenant-specific users who exist in individual
 * tenant databases.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('central_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('user'); // admin, user, etc.
            $table->string('interface_language', 5)->default('en');
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->json('metadata')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Indexes
            $table->index(['email', 'is_active']);
            $table->index('role');
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('central_users');
    }
};
