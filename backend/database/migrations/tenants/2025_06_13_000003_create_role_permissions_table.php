<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->json('conditions')->nullable()
                ->comment('Conditions under which the permission is granted through this role');
            $table->boolean('is_denied')->default(false)
                ->comment('Whether this permission is explicitly denied (overrides grants)');
            $table->timestamps();

            // Ensure unique role-permission combinations per tenant
            $table->unique(['tenant_id', 'role_id', 'permission_id'], 'unique_tenant_role_permission');
            
            // Add indexes for performance
            $table->index(['tenant_id', 'role_id']);
            $table->index(['tenant_id', 'permission_id']);
            $table->index('is_denied');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};