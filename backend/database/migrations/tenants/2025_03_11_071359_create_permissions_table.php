<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('group')
                ->comment('Logical grouping of permissions (e.g., content, users, settings)');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)
                ->comment('System permissions cannot be modified or deleted');
            $table->json('metadata')->nullable()
                ->comment('Additional permission configuration');
            $table->json('conditions')->nullable()
                ->comment('Conditions under which the permission is granted');
            $table->timestamps();

            // Add index for group queries
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};