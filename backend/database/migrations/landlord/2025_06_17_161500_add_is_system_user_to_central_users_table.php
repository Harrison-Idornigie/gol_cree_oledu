<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add is_system_user field to central_users table
 * 
 * This migration adds a boolean field to identify system users
 * used for automated operations like seeding, migrations, and
 * other background tasks that require user context.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('central_users', function (Blueprint $table) {
            $table->boolean('is_system_user')
                ->default(false)
                ->after('is_active')
                ->comment('Indicates if this is a system user for automated operations');
            
            // Add index for system user queries
            $table->index('is_system_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('central_users', function (Blueprint $table) {
            $table->dropIndex(['is_system_user']);
            $table->dropColumn('is_system_user');
        });
    }
};
