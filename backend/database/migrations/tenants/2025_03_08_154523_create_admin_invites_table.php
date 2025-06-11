<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_invites', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->string('email')->unique();
            $table->string('token')->unique();
            $table->foreignId('invited_by')->constrained('users');
            $table->string('membership');
            $table->string('status')->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_invites');
    }
};