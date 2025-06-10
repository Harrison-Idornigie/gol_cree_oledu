<?php
namespace Database\Seeders\Tenant;

use App\Models\Tenants\Exercise;
use App\Models\Tenants\Language;
use App\Models\Tenants\Section;
use App\Models\Tenants\Word;
use Illuminate\Support\Str;

class PictureExerciseSeeder extends BaseExerciseSeeder
{
    /**
     * The exercise type this seeder creates
     */
    protected string $exerciseType = Exercise::TYPE_PICTURE;

    /**
     * The number of exercises to create per language
     */
    protected int $exercisesPerLanguage = 5;

    /**
     * Create a single exercise
     */
    protected function createExercise(Section $section, $words, int $order, Language $language): void
    {
        // Get language-specific data
        $languageCode = $language->code;
        
        // Create a picture exercise based on the language
        $pictureData = $this->getPictureData($languageCode, $words, $order);
        
        // Create word mapping for clickable words
        $wordMapping = [];
        foreach ($words as $word) {
            $wordMapping[strtolower($word->text)] = $word->id;
        }
        
        // Create the exercise
        Exercise::create([
            'section_id' => $section->id,
            'lesson_id'  => $section->lesson_id,
            'title'      => "Picture - {$language->name} Exercise {$order}",
            'slug'       => Str::slug("picture-{$language->name}-exercise-{$order}"),
            'type'       => Exercise::TYPE_PICTURE,
            'content'    => [
                'question'     => $pictureData['question'],
                'mode'         => $pictureData['mode'],
                'images'       => $pictureData['images'] ?? [],
                'words'        => $pictureData['words'] ?? [],
                'target_image' => $pictureData['target_image'] ?? '',
                'language'     => $languageCode,
                'word_mapping' => $wordMapping,
            ],
            'answers'    => [
                'correct' => $pictureData['correct_answer'],
            ],
            'order'      => $order,
            'status'     => 'published',
        ]);
    }

    /**
     * Get picture data based on language
     */
    private function getPictureData(string $languageCode, $words, int $order): array
    {
        switch ($languageCode) {
            case 'crk': // Plains Cree
                return $this->getPlainsCreeData($words, $order);
            case 'es': // Spanish
                return $this->getSpanishData($words, $order);
            case 'en': // English
            default:
                return $this->getEnglishData($words, $order);
        }
    }

    /**
     * Get Plains Cree picture data
     */
    private function getPlainsCreeData($words, int $order): array
    {
        // Sample Plains Cree picture exercises
        $exercises = [
            // Word to Image exercises
            [
                'question' => 'Tānitē ōma "atim"?',
                'mode' => 'word_to_image',
                'images' => [
                    ['url' => '/images/animals/dog.jpg', 'alt' => 'Dog'],
                    ['url' => '/images/animals/cat.jpg', 'alt' => 'Cat'],
                    ['url' => '/images/animals/bird.jpg', 'alt' => 'Bird'],
                    ['url' => '/images/animals/horse.jpg', 'alt' => 'Horse'],
                ],
                'correct_answer' => 0, // Index of the correct image (dog)
            ],
            [
                'question' => 'Tānitē ōma "sīsīp"?',
                'mode' => 'word_to_image',
                'images' => [
                    ['url' => '/images/animals/rabbit.jpg', 'alt' => 'Rabbit'],
                    ['url' => '/images/animals/duck.jpg', 'alt' => 'Duck'],
                    ['url' => '/images/animals/fish.jpg', 'alt' => 'Fish'],
                    ['url' => '/images/animals/bear.jpg', 'alt' => 'Bear'],
                ],
                'correct_answer' => 1, // Index of the correct image (duck)
            ],
            // Image to Word exercises
            [
                'question' => 'Kīkwāy ōma?',
                'mode' => 'image_to_word',
                'target_image' => '/images/food/bread.jpg',
                'words' => ['pahkwêsikan', 'tohtôsâpoy', 'wiyâs', 'sîwipakwa'],
                'correct_answer' => 0, // Index of the correct word (bread)
            ],
            [
                'question' => 'Kīkwāy ōma?',
                'mode' => 'image_to_word',
                'target_image' => '/images/food/water.jpg',
                'words' => ['mîciwin', 'nîpiy', 'tohtôsâpoy', 'mînis'],
                'correct_answer' => 1, // Index of the correct word (water)
            ],
            [
                'question' => 'Kīkwāy ōma?',
                'mode' => 'image_to_word',
                'target_image' => '/images/nature/tree.jpg',
                'words' => ['maskosiy', 'asiniy', 'mistik', 'nipiy'],
                'correct_answer' => 2, // Index of the correct word (tree)
            ],
        ];
        
        // Return the exercise for this order (or the last one if order is too high)
        $index = min($order - 1, count($exercises) - 1);
        return $exercises[$index];
    }

    /**
     * Get Spanish picture data
     */
    private function getSpanishData($words, int $order): array
    {
        // Sample Spanish picture exercises
        $exercises = [
            // Word to Image exercises
            [
                'question' => '¿Dónde está "el perro"?',
                'mode' => 'word_to_image',
                'images' => [
                    ['url' => '/images/animals/dog.jpg', 'alt' => 'Perro'],
                    ['url' => '/images/animals/cat.jpg', 'alt' => 'Gato'],
                    ['url' => '/images/animals/bird.jpg', 'alt' => 'Pájaro'],
                    ['url' => '/images/animals/horse.jpg', 'alt' => 'Caballo'],
                ],
                'correct_answer' => 0, // Index of the correct image (dog)
            ],
            [
                'question' => '¿Dónde está "el pato"?',
                'mode' => 'word_to_image',
                'images' => [
                    ['url' => '/images/animals/rabbit.jpg', 'alt' => 'Conejo'],
                    ['url' => '/images/animals/duck.jpg', 'alt' => 'Pato'],
                    ['url' => '/images/animals/fish.jpg', 'alt' => 'Pez'],
                    ['url' => '/images/animals/bear.jpg', 'alt' => 'Oso'],
                ],
                'correct_answer' => 1, // Index of the correct image (duck)
            ],
            // Image to Word exercises
            [
                'question' => '¿Qué es esto?',
                'mode' => 'image_to_word',
                'target_image' => '/images/food/bread.jpg',
                'words' => ['el pan', 'la leche', 'la carne', 'la manzana'],
                'correct_answer' => 0, // Index of the correct word (bread)
            ],
            [
                'question' => '¿Qué es esto?',
                'mode' => 'image_to_word',
                'target_image' => '/images/food/water.jpg',
                'words' => ['la comida', 'el agua', 'la leche', 'el jugo'],
                'correct_answer' => 1, // Index of the correct word (water)
            ],
            [
                'question' => '¿Qué es esto?',
                'mode' => 'image_to_word',
                'target_image' => '/images/nature/tree.jpg',
                'words' => ['la hierba', 'la piedra', 'el árbol', 'la flor'],
                'correct_answer' => 2, // Index of the correct word (tree)
            ],
        ];
        
        // Return the exercise for this order (or the last one if order is too high)
        $index = min($order - 1, count($exercises) - 1);
        return $exercises[$index];
    }

    /**
     * Get English picture data
     */
    private function getEnglishData($words, int $order): array
    {
        // Sample English picture exercises
        $exercises = [
            // Word to Image exercises
            [
                'question' => 'Where is the "dog"?',
                'mode' => 'word_to_image',
                'images' => [
                    ['url' => '/images/animals/dog.jpg', 'alt' => 'Dog'],
                    ['url' => '/images/animals/cat.jpg', 'alt' => 'Cat'],
                    ['url' => '/images/animals/bird.jpg', 'alt' => 'Bird'],
                    ['url' => '/images/animals/horse.jpg', 'alt' => 'Horse'],
                ],
                'correct_answer' => 0, // Index of the correct image (dog)
            ],
            [
                'question' => 'Where is the "duck"?',
                'mode' => 'word_to_image',
                'images' => [
                    ['url' => '/images/animals/rabbit.jpg', 'alt' => 'Rabbit'],
                    ['url' => '/images/animals/duck.jpg', 'alt' => 'Duck'],
                    ['url' => '/images/animals/fish.jpg', 'alt' => 'Fish'],
                    ['url' => '/images/animals/bear.jpg', 'alt' => 'Bear'],
                ],
                'correct_answer' => 1, // Index of the correct image (duck)
            ],
            // Image to Word exercises
            [
                'question' => 'What is this?',
                'mode' => 'image_to_word',
                'target_image' => '/images/food/bread.jpg',
                'words' => ['bread', 'milk', 'meat', 'apple'],
                'correct_answer' => 0, // Index of the correct word (bread)
            ],
            [
                'question' => 'What is this?',
                'mode' => 'image_to_word',
                'target_image' => '/images/food/water.jpg',
                'words' => ['food', 'water', 'milk', 'juice'],
                'correct_answer' => 1, // Index of the correct word (water)
            ],
            [
                'question' => 'What is this?',
                'mode' => 'image_to_word',
                'target_image' => '/images/nature/tree.jpg',
                'words' => ['grass', 'rock', 'tree', 'flower'],
                'correct_answer' => 2, // Index of the correct word (tree)
            ],
        ];
        
        // Return the exercise for this order (or the last one if order is too high)
        $index = min($order - 1, count($exercises) - 1);
        return $exercises[$index];
    }
}
