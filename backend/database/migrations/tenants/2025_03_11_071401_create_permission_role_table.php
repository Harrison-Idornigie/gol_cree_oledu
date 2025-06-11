<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_membership', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('permission_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('membership_id')
                ->constrained()
                ->onDelete('cascade');
            $table->json('conditions')->nullable()
                ->comment('Additional conditions for this specific permission-membership combination');
            $table->boolean('is_denied')->default(false)
                ->comment('Explicitly deny this permission for this membership');
            $table->timestamps();

            // Create composite primary key
            $table->primary(['permission_id', 'membership_id']);

            // Add indexes for efficient queries
            $table->index('permission_id');
            $table->index('membership_id');
            $table->index('is_denied');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_membership');
    }
};