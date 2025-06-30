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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->comment('Reference to tenant in landlord database');
            $table->string('content_type');
            $table->unsignedBigInteger('content_id');
            $table->unsignedBigInteger('submitted_by');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('review_comment')->nullable();
            $table->enum('rejection_reason', ['content_issues', 'formatting_issues', 'accuracy_issues', 'other'])->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');

            // Index for polymorphic relationship
            $table->index(['content_type', 'content_id']);
        });

        // Add review_status column to content tables
        Schema::table('learning_paths', function (Blueprint $table) {
            $table->enum('review_status', ['none', 'pending', 'approved', 'rejected'])->default('none')->after('status');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->enum('review_status', ['none', 'pending', 'approved', 'rejected'])->default('none')->after('status');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->enum('review_status', ['none', 'pending', 'approved', 'rejected'])->default('none')->after('status');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->enum('review_status', ['none', 'pending', 'approved', 'rejected'])->default('none')->after('status');
        });

        // Removed sections table reference as sections have been removed from the data model

        Schema::table('exercises', function (Blueprint $table) {
            $table->enum('review_status', ['none', 'pending', 'approved', 'rejected'])->default('none')->after('status');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove review_status column from content tables
        Schema::table('learning_paths', function (Blueprint $table) {
            $table->dropColumn('review_status');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('review_status');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('review_status');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('review_status');
        });

        // Removed sections table reference as sections have been removed from the data model

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('review_status');
        });

        Schema::dropIfExists('reviews');
    }
};