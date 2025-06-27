<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantUserImpersonationTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenant_user_impersonation_tokens', function (Blueprint $table) {
            $table->string('token', 128)->primary();
            $table->uuid('tenant_id');
            $table->string('user_email'); // Use email instead of user_id for flexibility
            $table->unsignedBigInteger('impersonator_id'); // Central user performing impersonation
            $table->string('impersonator_email');
            $table->string('auth_guard')->default('tenant');
            $table->string('redirect_url')->nullable();
            $table->string('reason')->nullable(); // Support ticket #, reason for access
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('permissions')->nullable(); // Limited permissions during impersonation
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('impersonator_id')->references('id')->on('central_users')->onUpdate('cascade')->onDelete('cascade');

            // Indexes for performance
            $table->index(['tenant_id', 'user_email'], 'idx_tenant_user');
            $table->index(['impersonator_id', 'created_at'], 'idx_impersonator_date');
            $table->index(['expires_at', 'used_at'], 'idx_expires_used');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_user_impersonation_tokens');
    }
}
