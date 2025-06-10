<?php
namespace Database\Seeders\Tenant;

use App\Models\Tenants\Exercise;
use App\Models\Tenants\Language;
use App\Models\Tenants\Section;
use App\Models\Tenants\Word;
use Illuminate\Support\Str;

class ListeningExerciseSeeder extends BaseExerciseSeeder
{
    /**
     * The exercise type this seeder creates
     */
    protected string $exerciseType = Exercise::TYPE_LISTENING;

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
        
        // Create a listening exercise based on the language
        $listeningData = $this->getListeningData($languageCode, $words, $order);
        
        // Create word mapping for clickable words
        $wordMapping = [];
        foreach ($words as $word) {
            $wordMapping[strtolower($word->text)] = $word->id;
        }
        
        // Create the exercise
        Exercise::create([
            'section_id' => $section->id,
            'lesson_id'  => $section->lesson_id,
            'title'      => "Listening - {$language->name} Exercise {$order}",
            'slug'       => Str::slug("listening-{$language->name}-exercise-{$order}"),
            'type'       => Exercise::TYPE_LISTENING,
            'content'    => [
                'audio_url'    => $listeningData['audio_url'],
                'transcript'   => $listeningData['transcript'],
                'prompt'       => $listeningData['prompt'],
                'language'     => $languageCode,
                'difficulty'   => $listeningData['difficulty'],
                'word_mapping' => $wordMapping,
            ],
            'answers'    => [
                'correct'      => $listeningData['correct_answers'],
                'alternatives' => $listeningData['alternative_answers'],
            ],
            'order'      => $order,
            'status'     => 'published',
        ]);
    }

    /**
     * Get listening data based on language
     */
    private function getListeningData(string $languageCode, $words, int $order): array
    {
        switch ($languageCode) {
            case 'crk': // Plains Cree
                return $this->getPlainsCreeListeningData($words, $order);
            case 'es': // Spanish
                return $this->getSpanishListeningData($words, $order);
            case 'en': // English
            default:
                return $this->getEnglishListeningData($words, $order);
        }
    }

    /**
     * Get Plains Cree listening data
     */
    private function getPlainsCreeListeningData($words, int $order): array
    {
        // Sample Plains Cree phrases for listening exercises
        $phrases = [
            [
                'transcript' => 'Tanisi',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/cree/tanisi.mp3',
                'difficulty' => 'beginner',
                'correct_answers' => ['Tanisi', 'tanisi'],
                'alternative_answers' => ['Tansi', 'tansi'],
            ],
            [
                'transcript' => 'Tânisi kiya',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/cree/tanisi-kiya.mp3',
                'difficulty' => 'beginner',
                'correct_answers' => ['Tânisi kiya', 'tanisi kiya'],
                'alternative_answers' => ['Tansi kiya', 'tansi kiya'],
            ],
            [
                'transcript' => 'Nitôtem',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/cree/nitotem.mp3',
                'difficulty' => 'beginner',
                'correct_answers' => ['Nitôtem', 'nitotem'],
                'alternative_answers' => ['Nitotem', 'nitôtem'],
            ],
            [
                'transcript' => 'Tânisi kitôtên anohc',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/cree/tanisi-kitoten-anohc.mp3',
                'difficulty' => 'intermediate',
                'correct_answers' => ['Tânisi kitôtên anohc', 'tanisi kitoten anohc'],
                'alternative_answers' => ['Tanisi kitoten anohc', 'tansi kitoten anohc'],
            ],
            [
                'transcript' => 'Miywâsin ôma kîsikâw',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/cree/miywasin-oma-kisikaw.mp3',
                'difficulty' => 'intermediate',
                'correct_answers' => ['Miywâsin ôma kîsikâw', 'miywasin oma kisikaw'],
                'alternative_answers' => ['Miywasin oma kisikaw', 'miwasin oma kisikaw'],
            ],
        ];
        
        // Return the phrase for this order (or the last one if order is too high)
        $index = min($order - 1, count($phrases) - 1);
        return $phrases[$index];
    }

    /**
     * Get Spanish listening data
     */
    private function getSpanishListeningData($words, int $order): array
    {
        // Sample Spanish phrases for listening exercises
        $phrases = [
            [
                'transcript' => 'Hola',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/spanish/hola.mp3',
                'difficulty' => 'beginner',
                'correct_answers' => ['Hola', 'hola'],
                'alternative_answers' => [],
            ],
            [
                'transcript' => '¿Cómo estás?',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/spanish/como-estas.mp3',
                'difficulty' => 'beginner',
                'correct_answers' => ['¿Cómo estás?', 'como estas', 'Cómo estás'],
                'alternative_answers' => ['Como estas', 'como estas?', '¿como estas?'],
            ],
            [
                'transcript' => 'Buenos días',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/spanish/buenos-dias.mp3',
                'difficulty' => 'beginner',
                'correct_answers' => ['Buenos días', 'buenos dias'],
                'alternative_answers' => ['Buenos dias', 'buenos días'],
            ],
            [
                'transcript' => 'Me gusta aprender español',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/spanish/me-gusta-aprender-espanol.mp3',
                'difficulty' => 'intermediate',
                'correct_answers' => ['Me gusta aprender español', 'me gusta aprender espanol'],
                'alternative_answers' => ['Me gusta aprender espanol', 'me gusta aprender español'],
            ],
            [
                'transcript' => '¿Dónde está la biblioteca?',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/spanish/donde-esta-la-biblioteca.mp3',
                'difficulty' => 'intermediate',
                'correct_answers' => ['¿Dónde está la biblioteca?', 'donde esta la biblioteca'],
                'alternative_answers' => ['Donde esta la biblioteca', '¿donde esta la biblioteca?'],
            ],
        ];
        
        // Return the phrase for this order (or the last one if order is too high)
        $index = min($order - 1, count($phrases) - 1);
        return $phrases[$index];
    }

    /**
     * Get English listening data
     */
    private function getEnglishListeningData($words, int $order): array
    {
        // Sample English phrases for listening exercises
        $phrases = [
            [
                'transcript' => 'Hello',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/english/hello.mp3',
                'difficulty' => 'beginner',
                'correct_answers' => ['Hello', 'hello'],
                'alternative_answers' => [],
            ],
            [
                'transcript' => 'How are you?',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/english/how-are-you.mp3',
                'difficulty' => 'beginner',
                'correct_answers' => ['How are you?', 'how are you'],
                'alternative_answers' => ['How are you', 'how are you?'],
            ],
            [
                'transcript' => 'Good morning',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/english/good-morning.mp3',
                'difficulty' => 'beginner',
                'correct_answers' => ['Good morning', 'good morning'],
                'alternative_answers' => ['Good Morning', 'Good-morning'],
            ],
            [
                'transcript' => 'I like learning languages',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/english/i-like-learning-languages.mp3',
                'difficulty' => 'intermediate',
                'correct_answers' => ['I like learning languages', 'i like learning languages'],
                'alternative_answers' => ['I like learning language', 'i like learning language'],
            ],
            [
                'transcript' => 'Where is the library?',
                'prompt' => 'Listen and type what you hear.',
                'audio_url' => '/audio/english/where-is-the-library.mp3',
                'difficulty' => 'intermediate',
                'correct_answers' => ['Where is the library?', 'where is the library'],
                'alternative_answers' => ['Where is the library', 'where is the library?'],
            ],
        ];
        
        // Return the phrase for this order (or the last one if order is too high)
        $index = min($order - 1, count($phrases) - 1);
        return $phrases[$index];
    }
}