<?php
namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\Language;
use App\Models\Section;
use App\Models\Word;
use Illuminate\Support\Str;

class FillInBlankExerciseSeeder extends BaseExerciseSeeder
{
    /**
     * The exercise type this seeder creates
     */
    protected string $exerciseType = Exercise::TYPE_FILL_BLANK;

    /**
     * Create a single exercise
     */
    protected function createExercise(Section $section, $words, int $order, Language $language): void
    {
        // Select random words for this exercise
        $selectedWords = $words->random($this->wordsPerExercise);

        // Create a sentence with blanks
        $sentence = $this->createSentenceWithBlanks($selectedWords, $language);

        // Create the exercise
        Exercise::create([
            'section_id' => $section->id,
            'lesson_id'  => $section->lesson_id,
            'title'      => "Fill in the Blanks - {$language->name} Exercise {$order}",
            'slug'       => Str::slug("fill-in-blanks-{$language->name}-exercise-{$order}"),
            'type'       => Exercise::TYPE_FILL_BLANK,
            'content'    => [
                'text'     => $sentence['text'],
                'blanks'   => $sentence['blanks'],
                'word_ids' => $selectedWords->pluck('id')->toArray(),
            ],
            'answers'    => [
                'correct' => $sentence['answers'],
            ],
            'order'      => $order,
            'status'     => 'published',
        ]);
    }

    /**
     * Create a sentence with blanks for the exercise
     */
    private function createSentenceWithBlanks($words, Language $language): array
    {
        $blanks          = [];
        $answers         = [];
        $wordMapping     = [];
        $additionalWords = [];

        // Create different sentence templates based on language
        switch ($language->code) {
            case 'en':
                // Get additional words for the template
                $additionalWords = $this->getAdditionalWords($language->id, ['important', 'learn', 'free', 'time', 'beautiful', 'today', 'door', 'leave', 'table']);
                $text            = "Complete the sentence with the correct words: ____ is an {$additionalWords['important']->text} word to {$additionalWords['learn']->text}. I like to ____ when I have {$additionalWords['free']->text} {$additionalWords['time']->text}. The ____ is very {$additionalWords['beautiful']->text} {$additionalWords['today']->text}. Please ____ the {$additionalWords['door']->text} when you {$additionalWords['leave']->text}. The ____ is on the {$additionalWords['table']->text}.";
                break;
            case 'es':
                $additionalWords = $this->getAdditionalWords($language->id, ['importante', 'aprender', 'tiempo', 'libre', 'hermoso', 'hoy', 'puerta', 'salgas', 'mesa']);
                $text            = "Completa la oración con las palabras correctas: ____ es una palabra {$additionalWords['importante']->text} para {$additionalWords['aprender']->text}. Me gusta ____ cuando tengo {$additionalWords['tiempo']->text} {$additionalWords['libre']->text}. El ____ es muy {$additionalWords['hermoso']->text} {$additionalWords['hoy']->text}. Por favor ____ la {$additionalWords['puerta']->text} cuando {$additionalWords['salgas']->text}. El ____ está en la {$additionalWords['mesa']->text}.";
                break;
            case 'crk':
                $additionalWords = $this->getAdditionalWords($language->id, ['kihci', 'kiskinohamâkêhk', 'tipahaman', 'miyosâsin', 'anohc', 'iskwâhtêm', 'wâsâhk', 'mîcisowināhtikohk', 'astêw']);
                $text            = "Kîsihtâ ôma pîkiskwêwin: ____ êwako {$additionalWords['kihci']->text}-itwêwin ta-{$additionalWords['kiskinohamâkêhk']->text}. Nimiywêyihtên ta-____ ispî ê-{$additionalWords['tipahaman']->text}. ____ {$additionalWords['miyosâsin']->text} {$additionalWords['anohc']->text}. Mahti ____ {$additionalWords['iskwâhtêm']->text} {$additionalWords['wâsâhk']->text}. ____ {$additionalWords['mîcisowināhtikohk']->text} {$additionalWords['astêw']->text}.";
                break;
            default:
                $text = "Fill in the blanks with the correct words: ____ ____ ____ ____ ____.";
        }

        // Extract blanks and prepare answers
        $parts = explode('____', $text);
        $count = count($parts) - 1; // Number of blanks

        for ($i = 0; $i < $count; $i++) {
            $word      = $words[$i];
            $blanks[]  = $i;
            $answers[] = $word->text;
        }

        // Create word mapping for all words in the text
        // First add the blank words
        foreach ($words as $word) {
            $wordMapping[$word->text] = $word->id;
        }

        // Then add the additional words from the template
        foreach ($additionalWords as $word) {
            $wordMapping[$word->text] = $word->id;
        }

        return [
            'text'         => $text,
            'blanks'       => $blanks,
            'answers'      => $answers,
            'word_mapping' => $wordMapping,
        ];
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