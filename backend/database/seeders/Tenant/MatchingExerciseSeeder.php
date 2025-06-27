<?php
namespace Database\Seeders\Tenant;

use App\Models\Tenants\Exercise;
use App\Models\Tenants\Language;
use App\Models\Tenants\Section;
use App\Models\Tenants\Word;
use Illuminate\Support\Str;

class MatchingExerciseSeeder extends BaseExerciseSeeder
{
    /**
     * The exercise type this seeder creates
     */
    protected string $exerciseType = Exercise::TYPE_MATCHING;

    /**
     * The number of words to use per exercise
     */
    protected int $wordsPerExercise = 4; // Matching exercises typically use fewer items

    /**
     * Create a single exercise
     */
    protected function createExercise(Section $section, $words, int $order, Language $language): void
    {
        // Select random words for this exercise
        $selectedWords = $words->random($this->wordsPerExercise);

        // Get additional words for instructions
        $additionalWords = $this->getAdditionalWords($language->id, ['match', 'with', 'corresponding', 'translation', 'connect']);

        // Create items and matches arrays
        $items   = [];
        $matches = [];
        $correct = [];

        // For each word, create an item and a match
        foreach ($selectedWords as $index => $word) {
            $items[]         = $word->text;
            $matches[]       = $this->getTranslation($word);
            $correct[$index] = $index; // Correct mapping
        }

        // Create word mapping for all words in the exercise
        $wordMapping = [];

        // Add selected words to mapping
        foreach ($selectedWords as $word) {
            $wordMapping[$word->text] = $word->id;
        }

        // Add additional words from instructions
        foreach ($additionalWords as $word) {
            if ($word->id > 0) { // Only add real words, not placeholders
                $wordMapping[$word->text] = $word->id;
            }
        }

        // Create instructions based on language
        $instructions = $this->createInstructions($language, $additionalWords);

        // Create the exercise
        Exercise::create([
            'section_id' => $section->id,
            'lesson_id'  => $section->lesson_id,
            'title'      => "Matching - {$language->name} Exercise {$order}",
            'slug'       => Str::slug("matching-{$language->name}-exercise-{$order}"),
            'type'       => $this->exerciseType,
            'content'    => [
                'instructions' => $instructions,
                'items'        => $items,
                'matches'      => $matches,
                'word_ids'     => $selectedWords->pluck('id')->toArray(),
                'word_mapping' => $wordMapping,
            ],
            'answers'    => [
                'correct' => $correct,
            ],
            'order'      => $order,
            'status'     => 'published',
        ]);
    }

    /**
     * Get a translation for a word (simulated for this example)
     */
    private function getTranslation(Word $word): string
    {
        // In a real implementation, you would get an actual translation
        // For this example, we'll just use the word itself with a note
        return "{$word->text} (translation)";
    }

    /**
     * Create instructions for the matching exercise
     */
    private function createInstructions(Language $language, array $additionalWords): string
    {
        // Create different instruction templates based on language
        switch ($language->code) {
            case 'en':
                return "{$additionalWords['match']->text} each word {$additionalWords['with']->text} its {$additionalWords['corresponding']->text} {$additionalWords['translation']->text}. {$additionalWords['connect']->text} the items on the left with their matches on the right.";
            case 'es':
                return "{$additionalWords['match']->text} cada palabra {$additionalWords['with']->text} su {$additionalWords['translation']->text} {$additionalWords['corresponding']->text}. {$additionalWords['connect']->text} los elementos de la izquierda con sus coincidencias a la derecha.";
            case 'crk':
                return "{$additionalWords['match']->text} kahkiyaw itwêwina {$additionalWords['with']->text} iyasiwêwin {$additionalWords['corresponding']->text}. {$additionalWords['connect']->text} namahcîhk ohci {$additionalWords['with']->text} kihci-nîsohk.";
            default:
                return "Match the words with their translations.";
        }
    }

    /**
     * Get additional words for the template from the database
     */
    private function getAdditionalWords(int $languageId, array $wordTexts): array
    {
        $words = [];

        // Try to find words in the database
        $dbWords = Word::where('language_id', $languageId)
            ->whereIn('text', $wordTexts)
            ->get()
            ->keyBy('text');

        // For each requested word, use the database word if available, or create a placeholder
        foreach ($wordTexts as $text) {
            if ($dbWords->has($text)) {
                $words[$text] = $dbWords[$text];
            } else {
                // Create a placeholder word object
                $word = new Word([
                    'language_id'    => $languageId,
                    'text'           => $text,
                    'part_of_speech' => 'unknown',
                ]);
                $word->id     = 0; // Placeholder ID
                $words[$text] = $word;
            }
        }

        return $words;
    }
}