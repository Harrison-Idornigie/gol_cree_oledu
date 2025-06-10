<?php

namespace Database\Factories\Landlord;

use App\Models\Landlord\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Landlord\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company . ' School District';
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => $this->faker->sentence(),
            'contact_email' => $this->faker->companyEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'status' => $this->faker->randomElement(['active', 'trial', 'inactive']),
            'settings' => [
                'timezone' => $this->faker->timezone(),
                'language' => 'en',
                'features' => [
                    'analytics' => true,
                    'advanced_reporting' => $this->faker->boolean(),
                ]
            ],
            'trial_ends_at' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'subscription_ends_at' => $this->faker->optional()->dateTimeBetween('+30 days', '+1 year'),
        ];
    }

    /**
     * Indicate that the tenant is on trial.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
            'subscription_ends_at' => null,
        ]);
    }

    /**
     * Indicate that the tenant is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'trial_ends_at' => null,
            'subscription_ends_at' => now()->addYear(),
        ]);
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'trial_ends_at' => null,
            'subscription_ends_at' => null,
        ]);
    }

    /**
     * Indicate that the tenant has expired trial.
     */
    public function expiredTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => now()->subDays(5),
            'subscription_ends_at' => null,
        ]);
    }
}
