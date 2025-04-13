<?php
namespace Database\Factories;

use App\Models\Language;
use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

class WordFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Word::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'language_id'       => Language::factory(),
            'text'              => $this->faker->word(),
            'pronunciation_key' => null, // Will be set based on language
            'part_of_speech'    => $this->faker->randomElement(['noun', 'verb', 'adjective', 'adverb', 'pronoun', 'preposition', 'conjunction', 'interjection']),
            'metadata'          => [
                'difficulty'          => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
                'tags'                => $this->faker->randomElements(['common', 'academic', 'slang', 'formal', 'informal', 'technical'], $this->faker->numberBetween(1, 3)),
                'pronunciation_guide' => null, // Will be set based on language
                'pronunciation_notes' => null, // Will be set based on language
                'syllabics'           => null, // For Plains Cree
            ],
        ];
    }

    /**
     * Configure the model factory for English words.
     */
    public function english(): self
    {
        return $this->state(function (array $attributes) {
            $text          = $attributes['text'];
            $pronunciation = $this->generateEnglishPronunciation($text);

            return [
                'language_id'       => Language::where('code', 'en')->first()->id,
                'pronunciation_key' => null, // Not using IPA
                'metadata'          => array_merge($attributes['metadata'], [
                    'pronunciation_guide' => $pronunciation['guide'],
                    'pronunciation_notes' => $pronunciation['notes'],
                ]),
            ];
        });
    }

    /**
     * Configure the model factory for Spanish words.
     */
    public function spanish(): self
    {
        return $this->state(function (array $attributes) {
            $text          = $attributes['text'];
            $pronunciation = $this->generateSpanishPronunciation($text);

            return [
                'language_id'       => Language::where('code', 'es')->first()->id,
                'pronunciation_key' => null, // Not using IPA
                'metadata'          => array_merge($attributes['metadata'], [
                    'pronunciation_guide' => $pronunciation['guide'],
                    'pronunciation_notes' => $pronunciation['notes'],
                ]),
            ];
        });
    }

    /**
     * Configure the model factory for Plains Cree words.
     */
    public function plainsCree(): self
    {
        return $this->state(function (array $attributes) {
            $text          = $attributes['text'];
            $pronunciation = $this->generatePlainsCreePhonetics($text);

            return [
                'language_id'       => Language::where('code', 'crk')->first()->id,
                'pronunciation_key' => null, // Not using IPA
                'part_of_speech'    => $this->faker->randomElement([
                    'VAI', 'VTI', 'VTA', 'VII', // Verb types
                    'NA', 'NI',                 // Noun types
                    'PrA', 'PrI',               // Pronoun types
                    'IPC',                      // Particle
                ]),
                'metadata'          => array_merge($attributes['metadata'], [
                    'pronunciation_guide' => $pronunciation['guide'],
                    'pronunciation_notes' => $pronunciation['notes'],
                    'syllabics'           => $pronunciation['syllabics'],
                ]),
            ];
        });
    }

    /**
     * Generate English pronunciation guide
     */
    private function generateEnglishPronunciation(string $word): array
    {
        // Convert to uppercase for stressed syllable
        $syllables    = $this->splitIntoSyllables($word);
        $numSyllables = count($syllables);

        if ($numSyllables > 1) {
            // Typically stress the first syllable in English
            $syllables[0] = strtoupper($syllables[0]);
        } else {
            $syllables[0] = strtoupper($syllables[0]);
        }

        $guide = implode('-', $syllables);

        // Generate pronunciation notes
        $notes = $this->generateEnglishPronunciationNotes($word, $syllables);

        return [
            'guide' => $guide,
            'notes' => $notes,
        ];
    }

    /**
     * Generate Spanish pronunciation guide
     */
    private function generateSpanishPronunciation(string $word): array
    {
        // Convert to uppercase for stressed syllable
        $syllables    = $this->splitIntoSyllables($word);
        $numSyllables = count($syllables);

        // In Spanish, stress typically falls on the second-to-last syllable
        // if the word ends in a vowel, n, or s; otherwise on the last syllable
        $stressIndex = 0;
        if ($numSyllables > 1) {
            $lastChar = substr($word, -1);
            if (in_array($lastChar, ['a', 'e', 'i', 'o', 'u', 'n', 's'])) {
                $stressIndex = $numSyllables - 2;
            } else {
                $stressIndex = $numSyllables - 1;
            }
            $stressIndex             = max(0, min($stressIndex, $numSyllables - 1));
            $syllables[$stressIndex] = strtoupper($syllables[$stressIndex]);
        } else {
            $syllables[0] = strtoupper($syllables[0]);
        }

        $guide = implode('-', $syllables);

        // Generate pronunciation notes
        $notes = $this->generateSpanishPronunciationNotes($word, $syllables);

        return [
            'guide' => $guide,
            'notes' => $notes,
        ];
    }

    /**
     * Generate Plains Cree pronunciation guide and syllabics
     */
    private function generatePlainsCreePhonetics(string $word): array
    {
        // Convert to uppercase for stressed syllable
        $syllables    = $this->splitIntoSyllables($word);
        $numSyllables = count($syllables);

        // In Plains Cree, stress typically falls on the first syllable
        if ($numSyllables > 0) {
            $syllables[0] = strtoupper($syllables[0]);
        }

        $guide = implode('-', $syllables);

        // Generate pronunciation notes
        $notes = $this->generatePlainsCreeNotes($word, $syllables);

        // Generate mock syllabics (in a real app, you'd use a proper conversion library)
        $syllabics = $this->mockPlainsCreeToSyllabics($word);

        return [
            'guide'     => $guide,
            'notes'     => $notes,
            'syllabics' => $syllabics,
        ];
    }

    /**
     * Generate English pronunciation notes
     */
    private function generateEnglishPronunciationNotes(string $word, array $syllables): string
    {
        $notes = [];

        // Add stress note
        if (count($syllables) > 1) {
            $notes[] = "Stress is on the first syllable.";
        }

        // Add vowel sound notes
        if (strpos($word, 'a') !== false) {
            $notes[] = "The 'a' sounds like the 'a' in 'father'.";
        }
        if (strpos($word, 'e') !== false) {
            $notes[] = "The 'e' sounds like the 'e' in 'bed'.";
        }
        if (strpos($word, 'i') !== false) {
            $notes[] = "The 'i' sounds like the 'i' in 'sit'.";
        }
        if (strpos($word, 'o') !== false) {
            $notes[] = "The 'o' sounds like the 'o' in 'hot'.";
        }
        if (strpos($word, 'u') !== false) {
            $notes[] = "The 'u' sounds like the 'u' in 'put'.";
        }

        return implode(' ', $notes);
    }

    /**
     * Generate Spanish pronunciation notes
     */
    private function generateSpanishPronunciationNotes(string $word, array $syllables): string
    {
        $notes = [];

        // Add stress note
        if (count($syllables) > 1) {
            $lastChar = substr($word, -1);
            if (in_array($lastChar, ['a', 'e', 'i', 'o', 'u', 'n', 's'])) {
                $notes[] = "Stress is on the second-to-last syllable.";
            } else {
                $notes[] = "Stress is on the last syllable.";
            }
        }

        // Add specific Spanish sound notes
        if (strpos($word, 'ñ') !== false) {
            $notes[] = "The 'ñ' sounds like 'ny' in 'canyon'.";
        }
        if (strpos($word, 'll') !== false) {
            $notes[] = "The 'll' sounds like 'y' in 'yes'.";
        }
        if (strpos($word, 'j') !== false) {
            $notes[] = "The 'j' sounds like a strong 'h' sound.";
        }
        if (strpos($word, 'r') !== false && substr($word, 0, 1) === 'r') {
            $notes[] = "The initial 'r' is rolled.";
        }

        return implode(' ', $notes);
    }

    /**
     * Generate Plains Cree pronunciation notes
     */
    private function generatePlainsCreeNotes(string $word, array $syllables): string
    {
        $notes = [];

        // Add stress note
        if (count($syllables) > 1) {
            $notes[] = "Stress is on the first syllable.";
        }

        // Add vowel sound notes for Plains Cree
        if (strpos($word, 'â') !== false || strpos($word, 'ā') !== false) {
            $notes[] = "The 'â' or 'ā' is a long 'a' sound like in 'father'.";
        }
        if (strpos($word, 'ê') !== false || strpos($word, 'ē') !== false) {
            $notes[] = "The 'ê' or 'ē' is a long 'e' sound like in 'they'.";
        }
        if (strpos($word, 'î') !== false || strpos($word, 'ī') !== false) {
            $notes[] = "The 'î' or 'ī' is a long 'i' sound like in 'machine'.";
        }
        if (strpos($word, 'ô') !== false || strpos($word, 'ō') !== false) {
            $notes[] = "The 'ô' or 'ō' is a long 'o' sound like in 'go'.";
        }

        // Add consonant notes
        if (strpos($word, 'th') !== false) {
            $notes[] = "The 'th' is pronounced like 't' followed by a slight puff of air.";
        }

        return implode(' ', $notes);
    }

    /**
     * Split a word into syllables (simplified approach)
     */
    private function splitIntoSyllables(string $word): array
    {
        // This is a very simplified syllable splitter
        // In a real app, you'd use a proper linguistic library
        $word            = strtolower($word);
        $vowels          = ['a', 'e', 'i', 'o', 'u', 'y', 'á', 'é', 'í', 'ó', 'ú', 'ü', 'â', 'ê', 'î', 'ô', 'ā', 'ē', 'ī', 'ō'];
        $syllables       = [];
        $currentSyllable = '';

        $chars  = str_split($word);
        $length = count($chars);

        for ($i = 0; $i < $length; $i++) {
            $currentSyllable .= $chars[$i];

            // If this character is a vowel and the next character is a consonant or end of word
            if (in_array($chars[$i], $vowels) &&
                ($i == $length - 1 || ! in_array($chars[$i + 1], $vowels))) {

                // If there are at least two more characters and they're both consonants
                if ($i < $length - 2 && ! in_array($chars[$i + 1], $vowels) && ! in_array($chars[$i + 2], $vowels)) {
                    // Add the next consonant to this syllable
                    if ($i < $length - 1) {
                        $currentSyllable .= $chars[++$i];
                    }
                }

                $syllables[]     = $currentSyllable;
                $currentSyllable = '';
            }
        }

        // Add any remaining characters
        if ($currentSyllable !== '') {
            $syllables[] = $currentSyllable;
        }

        // If no syllables were found, treat the whole word as one syllable
        if (empty($syllables)) {
            $syllables[] = $word;
        }

        return $syllables;
    }

    /**
     * Mock conversion of Plains Cree to syllabics
     * In a real app, you'd use a proper conversion library
     */
    private function mockPlainsCreeToSyllabics(string $word): string
    {
        // This is just a placeholder that returns a mock syllabics representation
        // In a real app, you'd use a proper conversion library
        $syllabicsMap = [
            'a' => 'ᐊ', 'e' => 'ᐁ', 'i' => 'ᐃ', 'o' => 'ᐅ',
            'â' => 'ᐋ', 'ê' => 'ᐯ', 'î' => 'ᐄ', 'ô' => 'ᐆ',
            'p' => 'ᐱ', 't' => 'ᑎ', 'k' => 'ᑭ', 'm' => 'ᒥ',
            'n' => 'ᓂ', 's' => 'ᓯ', 'y' => 'ᔨ', 'w' => 'ᐃᐧ',
            'h' => 'ᐦ', 'c' => 'ᒋ',
        ];

        $result = '';
        foreach (str_split(strtolower($word)) as $char) {
            $result .= $syllabicsMap[$char] ?? $char;
        }

        return $result;
    }
}