<?php
namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\Language;
use App\Models\Section;
use App\Models\Word;
use Illuminate\Support\Str;

class MultipleChoiceExerciseSeeder extends BaseExerciseSeeder
{
    /**
     * The exercise type this seeder creates
     */
    protected string $exerciseType = Exercise::TYPE_MULTIPLE_CHOICE;

    /**
     * Create a single exercise
     */
    protected function createExercise(Section $section, $words, int $order, Language $language): void
    {
        // Select a word for the correct answer
        $correctWord = $words->random(1)->first();

        // Select 3 random words for distractors (wrong answers)
        $distractors = $words->whereNotIn('id', [$correctWord->id])->random(3);

        // Get additional words for the question template
        $additionalWords = $this->getAdditionalWords($language->id, ['means', 'select', 'correct', 'word', 'for']);

        // Create the question with word mapping
        $questionData = $this->createQuestion($correctWord, $language, $additionalWords);

        // Create options array with the correct answer and distractors
        $options = [$correctWord->text];
        foreach ($distractors as $distractor) {
            $options[] = $distractor->text;
        }

        // Shuffle options so the correct answer isn't always first
        shuffle($options);

        // Create word mapping for all words in the exercise
        $wordMapping = [
            $correctWord->text => $correctWord->id,
        ];

        // Add distractors to word mapping
        foreach ($distractors as $distractor) {
            $wordMapping[$distractor->text] = $distractor->id;
        }

        // Add additional words from the question
        foreach ($additionalWords as $word) {
            if ($word->id > 0) { // Only add real words, not placeholders
                $wordMapping[$word->text] = $word->id;
            }
        }

        // Create the exercise
        Exercise::create([
            'section_id' => $section->id,
            'lesson_id'  => $section->lesson_id,
            'title'      => "Multiple Choice - {$language->name} Exercise {$order}",
            'slug'       => Str::slug("multiple-choice-{$language->name}-exercise-{$order}"),
            'type'       => $this->exerciseType,
            'content'    => [
                'question'     => $questionData['text'],
                'options'      => $options,
                'word_ids'     => [$correctWord->id, ...$distractors->pluck('id')],
                'word_mapping' => $wordMapping,
                'translation'  => $questionData['translation'],
            ],
            'answers'    => [
                'correct' => $correctWord->text,
            ],
            'order'      => $order,
            'status'     => 'published',
        ]);
    }

    /**
     * Create a question for the multiple choice exercise
     */
    private function createQuestion(Word $word, Language $language, array $additionalWords): array
    {
        $translation = $this->getTranslation($word);

        // Create different question templates based on language
        switch ($language->code) {
            case 'en':
                $text = "Which {$additionalWords['word']->text} {$additionalWords['means']->text} \"{$translation}\"? {$additionalWords['select']->text} the {$additionalWords['correct']->text} {$additionalWords['word']->text} {$additionalWords['for']->text} the translation.";
                break;
            case 'es':
                $text = "¿Qué {$additionalWords['word']->text} {$additionalWords['means']->text} \"{$translation}\"? {$additionalWords['select']->text} la {$additionalWords['word']->text} {$additionalWords['correct']->text} {$additionalWords['for']->text} la traducción.";
                break;
            case 'crk':
                $text = "Tânima {$additionalWords['word']->text} êwako \"{$translation}\"? {$additionalWords['select']->text} ka-{$additionalWords['correct']->text} {$additionalWords['word']->text} {$additionalWords['for']->text} iyasiwêwin.";
                break;
            default:
                $text = "{$additionalWords['select']->text} the {$additionalWords['correct']->text} {$additionalWords['word']->text} {$additionalWords['for']->text} \"{$translation}\":";
        }

        return [
            'text'        => $text,
            'translation' => $translation,
        ];
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