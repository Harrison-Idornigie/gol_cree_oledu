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
        Schema::table('tenants', function (Blueprint $table) {
            // Add subdomain field for subdomain-based tenant resolution
            $table->string('subdomain')->nullable()->unique()->after('domain');
            
            // Add custom_domain field for custom domain tenant resolution
            $table->string('custom_domain')->nullable()->unique()->after('subdomain');
            
            // Add indexes for performance
            $table->index('subdomain');
            $table->index('custom_domain');
            $table->index(['status', 'subdomain']);
            $table->index(['status', 'custom_domain']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['status', 'custom_domain']);
            $table->dropIndex(['status', 'subdomain']);
            $table->dropIndex(['custom_domain']);
            $table->dropIndex(['subdomain']);
            
            // Drop columns
            $table->dropColumn(['subdomain', 'custom_domain']);
        });
    }
};
