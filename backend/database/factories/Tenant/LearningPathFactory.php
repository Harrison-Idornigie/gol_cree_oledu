<?php

namespace Database\Factories\Tenant;

use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use Illuminate\Database\Eloquent\Factories\Factory;

class LearningPathFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LearningPath::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'language_id' => fn() => Language::factory()->create()->id,
            'description' => $this->faker->paragraph(),
            'target_level' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
            'status' => 'published',
            'review_status' => 'approved',
        ];
    }
}
