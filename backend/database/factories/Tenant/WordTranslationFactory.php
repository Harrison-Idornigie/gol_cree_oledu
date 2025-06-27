<?php

namespace Database\Factories\Tenant;

use App\Models\Tenants\Language;
use App\Models\Tenants\Word;
use App\Models\Tenants\WordTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

class WordTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WordTranslation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'word_id' => Word::factory(),
            'language_id' => Language::factory(),
            'text' => $this->faker->word(),
            'pronunciation_key' => null,
            'context_notes' => $this->faker->optional(0.5)->sentence(),
            'usage_examples' => $this->faker->optional(0.3)->sentences(2),
            'translation_order' => 1,
        ];
    }

    /**
     * Configure the model factory for English translations.
     */
    public function english(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'language_id' => Language::where('code', 'en')->first()->id,
                'pronunciation_key' => function (array $attributes) {
                    // Simple IPA-like pronunciation for English
                    $text = $attributes['text'];
                    $simplified = preg_replace('/[^a-zA-Z]/', '', $text);
                    $phonetic = strtolower($simplified);
                    // Apply some basic English phonetic rules
                    $phonetic = str_replace(['a', 'e', 'i', 'o', 'u'], ['æ', 'ɛ', 'ɪ', 'ɒ', 'ʌ'], $phonetic);
                    return '/' . $phonetic . '/';
                },
            ];
        });
    }

    /**
     * Configure the model factory for Spanish translations.
     */
    public function spanish(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'language_id' => Language::where('code', 'es')->first()->id,
                'pronunciation_key' => function (array $attributes) {
                    // Simple IPA-like pronunciation for Spanish
                    $text = $attributes['text'];
                    $simplified = preg_replace('/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/', '', $text);
                    $phonetic = strtolower($simplified);
                    // Apply some basic Spanish phonetic rules
                    $phonetic = str_replace(
                        ['a', 'e', 'i', 'o', 'u', 'ñ', 'j', 'll', 'z', 'c', 'h'],
                        ['a', 'e', 'i', 'o', 'u', 'ɲ', 'x', 'ʎ', 'θ', 'k', ''],
                        $phonetic
                    );
                    return '/' . $phonetic . '/';
                },
            ];
        });
    }

    /**
     * Configure the model factory for Plains Cree translations.
     */
    public function plainsCree(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'language_id' => Language::where('code', 'crk')->first()->id,
                'pronunciation_key' => function (array $attributes) {
                    // Simple IPA-like pronunciation for Plains Cree
                    $text = $attributes['text'];
                    $simplified = preg_replace('/[^a-zA-ZêîôâēīōāáéíóúÊÎÔÂĒĪŌĀÁÉÍÓÚ]/', '', $text);
                    $phonetic = strtolower($simplified);
                    // Apply some basic Plains Cree phonetic rules
                    $phonetic = str_replace(
                        ['ê', 'î', 'ô', 'â', 'ē', 'ī', 'ō', 'ā', 'á', 'é', 'í', 'ó', 'ú'],
                        ['e:', 'i:', 'o:', 'a:', 'e:', 'i:', 'o:', 'a:', 'a', 'e', 'i', 'o', 'u'],
                        $phonetic
                    );
                    return '/' . $phonetic . '/';
                },
            ];
        });
    }
}
