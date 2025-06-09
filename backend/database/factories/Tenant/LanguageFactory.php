<?php
namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

class LanguageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Language::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->languageCode(),
            'native_name' => $this->faker->word(),
            'code'        => $this->faker->unique()->languageCode(),
            'is_active'   => true,
        ];
    }
}
