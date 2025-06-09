<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add tenant_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'email']); // Composite index for tenant-scoped queries
        });

        // Add tenant_id to learning_paths table
        Schema::table('learning_paths', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'status']); // For filtering published content per tenant
        });

        // Add tenant_id to languages table (for tenant-specific language customizations)
        Schema::table('languages', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'is_active']);
        });

        // Add tenant_id to roles table (for tenant-specific roles)
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'slug']);
        });

        // Add tenant_id to audit_logs table
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'email']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('learning_paths', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('languages', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'is_active']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'slug']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropColumn('tenant_id');
        });
    }
};
