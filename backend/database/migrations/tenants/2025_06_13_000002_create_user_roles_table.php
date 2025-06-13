<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->json('conditions')->nullable()
                ->comment('Conditions under which the role is assigned');
            $table->boolean('is_active')->default(true)
                ->comment('Whether the role assignment is currently active');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()
                ->comment('Optional expiration date for temporary role assignments');
            $table->timestamps();

            // Ensure unique user-role combinations per tenant
            $table->unique(['tenant_id', 'user_id', 'role_id'], 'unique_tenant_user_role');
            
            // Add indexes for performance
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'role_id']);
            $table->index('is_active');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};