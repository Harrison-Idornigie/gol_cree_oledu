<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)
                ->comment('System memberships cannot be modified or deleted');
            $table->json('metadata')->nullable()
                ->comment('Additional membership configuration');
            $table->timestamps();

            $table->index(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};