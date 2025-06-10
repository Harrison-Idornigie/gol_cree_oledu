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
        // Core language management
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->string('code', 5);  // ISO code (e.g., 'en', 'ja', 'es')
            $table->string('name');     // Display name
            $table->string('native_name'); // Name in the language itself
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code');
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('language_pairs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->foreignId('source_language_id')->constrained('languages');
            $table->foreignId('target_language_id')->constrained('languages');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['source_language_id', 'target_language_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('language_pairs');
        Schema::dropIfExists('languages');
    }
};
