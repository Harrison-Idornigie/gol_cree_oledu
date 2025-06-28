<?php

namespace Database\Factories\Tenant;

use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_path_id' => fn() => LearningPath::factory()->create()->id,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'order' => $this->faker->numberBetween(1, 10),
            'status' => 'published',
            'review_status' => 'approved',
        ];
    }
}
