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
        // Achievements system
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description');
            $table->json('requirements');
            $table->json('rewards');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('achievement_id')->constrained()->onDelete('cascade');
            $table->timestamp('earned_at');
            $table->json('metadata')->nullable(); // Store additional data about how it was earned
            $table->unique(['user_id', 'achievement_id']);
        });

        // XP and Rewards system
        Schema::create('xp_rules', function (Blueprint $table) {
            $table->id();
            $table->string('action')->unique(); // e.g., 'lesson_completion', 'perfect_score'
            $table->integer('base_xp');
            $table->json('multipliers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Leagues system
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('tier');
            $table->json('requirements');
            $table->json('rewards');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('league_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('league_id')->constrained()->onDelete('cascade');
            $table->integer('current_rank')->nullable();
            $table->integer('weekly_xp')->default(0);
            $table->timestamp('joined_at');
            $table->timestamp('promoted_at')->nullable();
            $table->unique(['user_id', 'league_id']);
        });

        // Streak system
        Schema::create('streak_rules', function (Blueprint $table) {
            $table->id();
            $table->integer('freeze_cost');
            $table->integer('repair_window_hours');
            $table->json('bonus_schedule');
            $table->json('xp_multipliers');
            $table->timestamps();
        });

        Schema::create('user_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('current_streak');
            $table->integer('longest_streak');
            $table->date('last_activity_date');
            $table->boolean('freeze_used')->default(false);
            $table->timestamp('freeze_expires_at')->nullable();
            $table->timestamps();
        });

        // Daily Goals system
        Schema::create('daily_goals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('xp_target');
            $table->json('rewards');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_daily_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('daily_goal_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('progress')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unique(['user_id', 'daily_goal_id', 'date']);
        });

        // Bonus Events system
        Schema::create('bonus_events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->json('bonuses');
            $table->json('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_events');
        Schema::dropIfExists('user_daily_goals');
        Schema::dropIfExists('daily_goals');
        Schema::dropIfExists('user_streaks');
        Schema::dropIfExists('streak_rules');
        Schema::dropIfExists('league_memberships');
        Schema::dropIfExists('leagues');
        Schema::dropIfExists('xp_rules');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
    }
};