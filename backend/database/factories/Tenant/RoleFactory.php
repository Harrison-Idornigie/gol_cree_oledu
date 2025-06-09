<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->jobTitle();
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => $this->faker->sentence(),
            'is_system' => false,
            'metadata' => [
                'created_by' => 'factory',
                'permissions_count' => $this->faker->numberBetween(1, 10),
            ],
        ];
    }

    /**
     * Indicate that the role is a system role.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Create a super admin role.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Super Administrator',
            'slug' => 'super-admin',
            'description' => 'System super administrator with full access',
            'is_system' => true,
        ]);
    }

    /**
     * Create a tenant admin role.
     */
    public function tenantAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Tenant Administrator',
            'slug' => 'tenant-admin',
            'description' => 'Administrator for a specific tenant',
            'is_system' => true,
        ]);
    }

    /**
     * Create a team role.
     */
    public function team(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Team Member',
            'slug' => 'team',
            'description' => 'Team member with content creation access',
            'is_system' => true,
        ]);
    }

    /**
     * Create a student role.
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Student',
            'slug' => 'student',
            'description' => 'Student with learning access',
            'is_system' => true,
        ]);
    }
}
