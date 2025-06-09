<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('role_id')
                ->constrained()
                ->onDelete('cascade');
            $table->json('conditions')->nullable()
                ->comment('Additional conditions for this specific permission-role combination');
            $table->boolean('is_denied')->default(false)
                ->comment('Explicitly deny this permission for this role');
            $table->timestamps();

            // Create composite primary key
            $table->primary(['permission_id', 'role_id']);

            // Add indexes for efficient queries
            $table->index('permission_id');
            $table->index('role_id');
            $table->index('is_denied');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};