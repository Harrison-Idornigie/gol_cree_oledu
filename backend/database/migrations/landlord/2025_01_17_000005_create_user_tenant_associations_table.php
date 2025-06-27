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
        Schema::create('user_tenant_associations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->uuid('tenant_id');
            $table->string('tenant_slug')->index();
            $table->string('membership')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            
            // Composite indexes for performance
            $table->unique(['email', 'tenant_id']);
            $table->index(['email', 'is_active']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['email', 'last_accessed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tenant_associations');
    }
};
