<?php

namespace Database\Factories\Tenant;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenants\GuideBookEntry>
 */
class GuideBookEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'content' => $this->faker->paragraphs(3, true),
            'description' => $this->faker->sentence(),
            'words_introduced' => [1, 2, 3], // Example word IDs from Words table
            'words_reused' => [4, 5], // Example word IDs being reused
            'word_count' => 5,
            'difficulty_level' => $this->faker->numberBetween(1, 5),
            'tags' => ['grammar', 'beginner'],
            'references' => ['page 1', 'section 2'],
            'order' => $this->faker->numberBetween(1, 10),
            'category' => $this->faker->randomElement(['lesson_guide', 'grammar', 'pronunciation', 'culture', 'conversation', 'reference']),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
        ];
    }
}
