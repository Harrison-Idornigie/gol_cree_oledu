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
            // Add custom fields for better tenant management
            $table->string('name')->after('id');
            $table->string('slug')->unique()->after('name');
            $table->string('database_name')->nullable()->after('slug');
            $table->text('description')->nullable()->after('database_name');
            $table->string('contact_email')->nullable()->after('description');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->text('address')->nullable()->after('contact_phone');
            $table->json('settings')->nullable()->after('address');
            $table->enum('status', ['active', 'inactive', 'suspended', 'trial'])->default('trial')->after('settings');
            $table->timestamp('trial_ends_at')->nullable()->after('status');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
            
            // Add indexes for performance
            $table->index('slug');
            $table->index('database_name');
            $table->index('status');
            $table->index('trial_ends_at');
            $table->index('subscription_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropIndex(['database_name']);
            $table->dropIndex(['status']);
            $table->dropIndex(['trial_ends_at']);
            $table->dropIndex(['subscription_ends_at']);
            
            $table->dropColumn([
                'name',
                'slug',
                'database_name',
                'description',
                'contact_email',
                'contact_phone',
                'address',
                'settings',
                'status',
                'trial_ends_at',
                'subscription_ends_at',
            ]);
        });
    }
};
