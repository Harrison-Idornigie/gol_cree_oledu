<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->unsignedBigInteger('central_user_id')
                ->nullable()
                ->comment('Link to central_users table for admin users');
            $table->string('google_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->enum('membership', ['user', 'team', 'student', 'admin'])->default('user'); // Add membership column with default value
            $table->integer('points')->default(0);
            $table->string('avatar_url')->nullable();
            $table->string('interface_language', 5)->default('en');
            $table->timestamps();

            $table->index(['tenant_id', 'email']);
            $table->index('central_user_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
