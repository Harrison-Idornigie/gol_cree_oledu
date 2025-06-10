<?php
namespace Database\Seeders\Tenant;

use App\Models\Tenants\Exercise;
use App\Models\Tenants\Language;
use App\Models\Tenants\Section;
use App\Models\Tenants\Word;
use Illuminate\Support\Str;

class ConversationExerciseSeeder extends BaseExerciseSeeder
{
    /**
     * The exercise type this seeder creates
     */
    protected string $exerciseType = Exercise::TYPE_CONVERSATION;

    /**
     * The number of exercises to create per language
     */
    protected int $exercisesPerLanguage = 3;

    /**
     * Create a single exercise
     */
    protected function createExercise(Section $section, $words, int $order, Language $language): void
    {
        // Get language-specific data
        $languageCode = $language->code;
        
        // Create a conversation exercise based on the language
        $conversationData = $this->getConversationData($languageCode, $words);
        
        // Create word mapping for clickable words
        $wordMapping = [];
        foreach ($words as $word) {
            $wordMapping[strtolower($word->text)] = $word->id;
        }
        
        // Create the exercise
        Exercise::create([
            'section_id' => $section->id,
            'lesson_id'  => $section->lesson_id,
            'title'      => "Conversation - {$language->name} Exercise {$order}",
            'slug'       => Str::slug("conversation-{$language->name}-exercise-{$order}"),
            'type'       => Exercise::TYPE_CONVERSATION,
            'content'    => [
                'title'        => $conversationData['title'],
                'description'  => $conversationData['description'],
                'steps'        => $conversationData['steps'],
                'word_mapping' => $wordMapping,
                'language'     => $languageCode,
            ],
            'answers'    => [
                'steps' => $conversationData['answers'],
            ],
            'order'      => $order,
            'status'     => 'published',
        ]);
    }

    /**
     * Get conversation data based on language
     */
    private function getConversationData(string $languageCode, $words): array
    {
        switch ($languageCode) {
            case 'crk': // Plains Cree
                return $this->getPlainsCreeConversation($words);
            case 'es': // Spanish
                return $this->getSpanishConversation($words);
            case 'en': // English
            default:
                return $this->getEnglishConversation($words);
        }
    }

    /**
     * Get Plains Cree conversation data
     */
    private function getPlainsCreeConversation($words): array
    {
        // Select some words to use in the conversation
        $greeting = $words->where('text', 'tanisi')->first() ?? $words->random();
        $friend = $words->where('text', 'nitôtem')->first() ?? $words->random();
        $water = $words->where('text', 'nîpiy')->first() ?? $words->random();
        $food = $words->where('text', 'mîcisowin')->first() ?? $words->random();
        $good = $words->where('text', 'miywâsin')->first() ?? $words->random();
        
        return [
            'title' => 'At the Restaurant',
            'description' => 'Practice a conversation in Plains Cree at a restaurant.',
            'steps' => [
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Nîhîy',
                        'text' => "{$greeting->text}! {$friend->text}.",
                        'audio_url' => null,
                        'translation' => 'Hello! My friend.',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Iskwew',
                        'text' => "{$greeting->text}! Tânisi kiya?",
                        'audio_url' => null,
                        'translation' => 'Hello! How are you?',
                    ],
                ],
                [
                    'type' => 'question',
                    'content' => [
                        'question' => 'What did Iskwew ask?',
                        'hint' => 'She asked how Nîhîy is doing.',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Nîhîy',
                        'text' => "Namôya nânitaw. Kinôhte-{$food->text} cî?",
                        'audio_url' => null,
                        'translation' => 'I am fine. Do you want to eat?',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Iskwew',
                        'text' => "Êhê, ninôhte-{$food->text}.",
                        'audio_url' => null,
                        'translation' => 'Yes, I want to eat.',
                    ],
                ],
                [
                    'type' => 'choice',
                    'content' => [
                        'question' => 'What does "ninôhte-mîcisowin" mean?',
                        'options' => [
                            'I want to sleep',
                            'I want to eat',
                            'I want to drink',
                            'I want to go home',
                        ],
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Nîhîy',
                        'text' => "Kinôhte-{$water->text} cî?",
                        'audio_url' => null,
                        'translation' => 'Do you want water?',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Iskwew',
                        'text' => "Êhê, {$water->text} {$good->text}.",
                        'audio_url' => null,
                        'translation' => 'Yes, water is good.',
                    ],
                ],
                [
                    'type' => 'question',
                    'content' => [
                        'question' => 'What did Iskwew say about water?',
                        'hint' => 'She said something about the quality of water.',
                    ],
                ],
            ],
            'answers' => [
                2 => [
                    'correct' => ['How are you', 'How is he', 'How are you doing'],
                ],
                5 => [
                    'correct' => 1, // Index of the correct option (I want to eat)
                ],
                8 => [
                    'correct' => ['Water is good', 'The water is good'],
                ],
            ],
        ];
    }

    /**
     * Get Spanish conversation data
     */
    private function getSpanishConversation($words): array
    {
        // Select some words to use in the conversation
        $hello = $words->where('text', 'hola')->first() ?? $words->random();
        $friend = $words->where('text', 'amigo')->first() ?? $words->random();
        $water = $words->where('text', 'agua')->first() ?? $words->random();
        $food = $words->where('text', 'comida')->first() ?? $words->random();
        $good = $words->where('text', 'bueno')->first() ?? $words->random();
        
        return [
            'title' => 'En el Restaurante',
            'description' => 'Practice a conversation in Spanish at a restaurant.',
            'steps' => [
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Carlos',
                        'text' => "{$hello->text}, {$friend->text}!",
                        'audio_url' => null,
                        'translation' => 'Hello, friend!',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'María',
                        'text' => "{$hello->text}! ¿Cómo estás?",
                        'audio_url' => null,
                        'translation' => 'Hello! How are you?',
                    ],
                ],
                [
                    'type' => 'question',
                    'content' => [
                        'question' => 'What did María ask?',
                        'hint' => 'She asked about how Carlos is feeling.',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Carlos',
                        'text' => "Estoy bien. ¿Quieres {$food->text}?",
                        'audio_url' => null,
                        'translation' => 'I am fine. Do you want food?',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'María',
                        'text' => "Sí, quiero {$food->text}.",
                        'audio_url' => null,
                        'translation' => 'Yes, I want food.',
                    ],
                ],
                [
                    'type' => 'choice',
                    'content' => [
                        'question' => 'What does "quiero comida" mean?',
                        'options' => [
                            'I want to sleep',
                            'I want to eat',
                            'I want food',
                            'I want to go home',
                        ],
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Carlos',
                        'text' => "¿Quieres {$water->text}?",
                        'audio_url' => null,
                        'translation' => 'Do you want water?',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'María',
                        'text' => "Sí, el {$water->text} es {$good->text}.",
                        'audio_url' => null,
                        'translation' => 'Yes, the water is good.',
                    ],
                ],
                [
                    'type' => 'question',
                    'content' => [
                        'question' => 'What did María say about water?',
                        'hint' => 'She said something about the quality of water.',
                    ],
                ],
            ],
            'answers' => [
                2 => [
                    'correct' => ['How are you', 'How are you doing'],
                ],
                5 => [
                    'correct' => 2, // Index of the correct option (I want food)
                ],
                8 => [
                    'correct' => ['The water is good', 'Water is good'],
                ],
            ],
        ];
    }

    /**
     * Get English conversation data
     */
    private function getEnglishConversation($words): array
    {
        // Select some words to use in the conversation
        $hello = $words->where('text', 'hello')->first() ?? $words->random();
        $friend = $words->where('text', 'friend')->first() ?? $words->random();
        $water = $words->where('text', 'water')->first() ?? $words->random();
        $food = $words->where('text', 'food')->first() ?? $words->random();
        $good = $words->where('text', 'good')->first() ?? $words->random();
        
        return [
            'title' => 'At the Restaurant',
            'description' => 'Practice a conversation in English at a restaurant.',
            'steps' => [
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'John',
                        'text' => "{$hello->text}, {$friend->text}!",
                        'audio_url' => null,
                        'translation' => 'Hello, friend!',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Sarah',
                        'text' => "{$hello->text}! How are you?",
                        'audio_url' => null,
                        'translation' => 'Hello! How are you?',
                    ],
                ],
                [
                    'type' => 'question',
                    'content' => [
                        'question' => 'What did Sarah ask?',
                        'hint' => 'She asked about John\'s well-being.',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'John',
                        'text' => "I'm fine. Would you like some {$food->text}?",
                        'audio_url' => null,
                        'translation' => 'I am fine. Do you want food?',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Sarah',
                        'text' => "Yes, I would like some {$food->text}.",
                        'audio_url' => null,
                        'translation' => 'Yes, I want food.',
                    ],
                ],
                [
                    'type' => 'choice',
                    'content' => [
                        'question' => 'What does Sarah want?',
                        'options' => [
                            'She wants to leave',
                            'She wants to sleep',
                            'She wants food',
                            'She wants to talk',
                        ],
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'John',
                        'text' => "Would you like some {$water->text}?",
                        'audio_url' => null,
                        'translation' => 'Do you want water?',
                    ],
                ],
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Sarah',
                        'text' => "Yes, {$water->text} is {$good->text}.",
                        'audio_url' => null,
                        'translation' => 'Yes, water is good.',
                    ],
                ],
                [
                    'type' => 'question',
                    'content' => [
                        'question' => 'What did Sarah say about water?',
                        'hint' => 'She said something about the quality of water.',
                    ],
                ],
            ],
            'answers' => [
                2 => [
                    'correct' => ['How are you', 'How are you doing'],
                ],
                5 => [
                    'correct' => 2, // Index of the correct option (She wants food)
                ],
                8 => [
                    'correct' => ['Water is good', 'The water is good'],
                ],
            ],
        ];
    }
}
