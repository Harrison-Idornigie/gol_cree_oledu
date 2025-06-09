<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        // Add tenant_id to units table
        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
        });

        // Add tenant_id to topics table
        Schema::table('topics', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
        });

        // Add tenant_id to lessons table
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
        });

        // Add tenant_id to exercises table
        Schema::table('exercises', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
        });

        // Add tenant_id to words table
        Schema::table('words', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'language_id']);
        });

        // Add tenant_id to sentences table
        Schema::table('sentences', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'language_id']);
        });

        // Add tenant_id to audit_logs table
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
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

        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('words', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'language_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('sentences', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'language_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropColumn('tenant_id');
        });
    }
};
