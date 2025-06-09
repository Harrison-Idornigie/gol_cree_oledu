<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // School District Name
            $table->string('slug')->unique(); // URL-friendly identifier
            $table->string('domain')->nullable()->unique(); // Custom domain
            $table->text('description')->nullable();
            $table->json('settings')->nullable(); // Tenant-specific configurations
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->json('contact_info')->nullable(); // Admin contact details
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
