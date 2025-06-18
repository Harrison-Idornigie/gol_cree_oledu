<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add 'system' membership type to tenant users table
 * 
 * This migration adds 'system' as a valid membership type for tenant users,
 * allowing system users to be created for automated operations like seeding,
 * migrations, and other background tasks that require user context.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to modify the enum by recreating it
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN membership ENUM('user', 'team', 'student', 'admin', 'system') DEFAULT 'user'");
        } else {
            // For other databases, use standard schema modification
            Schema::table('users', function (Blueprint $table) {
                $table->enum('membership', ['user', 'team', 'student', 'admin', 'system'])
                    ->default('user')
                    ->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove any existing system users before reverting the enum
        DB::table('users')->where('membership', 'system')->delete();
        
        // Revert the enum to original values
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN membership ENUM('user', 'team', 'student', 'admin') DEFAULT 'user'");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('membership', ['user', 'team', 'student', 'admin'])
                    ->default('user')
                    ->change();
            });
        }
    }
};
