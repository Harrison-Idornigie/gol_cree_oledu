<?php

namespace Database\Factories;

use App\Models\UserStreak;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserStreakFactory extends Factory
{
    protected $model = UserStreak::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_activity_date' => now(),  // Set default date to now
            'freeze_used' => false,
            'freeze_expires_at' => null,
        ];
    }

    /**
     * Create a streak with a specific count.
     */
    public function withStreak(int $streak): static
    {
        return $this->state(fn (array $attributes) => [
            'current_streak' => $streak,
            'longest_streak' => $streak,
            'last_activity_date' => now()->subDay(),
        ]);
    }

    /**
     * Create a streak with an active freeze.
     */
    public function withActiveFreeze(): static
    {
        return $this->state(fn (array $attributes) => [
            'freeze_used' => true,
            'freeze_expires_at' => now()->addDays(3),
        ]);
    }
}