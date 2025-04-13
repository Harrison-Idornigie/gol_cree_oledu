<?php
namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Section::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(3);
        return [
            'lesson_id'         => Lesson::factory(),
            'title'             => $title,
            'slug'              => \Illuminate\Support\Str::slug($title),
            'description'       => $this->faker->paragraph(),
            'content'           => $this->faker->paragraphs(3, true),
            'type'              => $this->faker->randomElement(['theory', 'practice', 'quiz']),
            'order'             => $this->faker->numberBetween(1, 10),
            'requires_previous' => true,
            'xp_reward'         => $this->faker->numberBetween(5, 20),
            'estimated_time'    => $this->faker->numberBetween(5, 15),
            'allow_retry'       => true,
            'show_solution'     => true,
            'is_published'      => true,
            'difficulty_level'  => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
        ];
    }
}
