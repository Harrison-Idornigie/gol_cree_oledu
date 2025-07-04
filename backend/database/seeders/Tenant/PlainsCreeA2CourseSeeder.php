<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\User;
use App\Models\Tenants\LearningPath;
use Illuminate\Support\Facades\Auth;

class PlainsCreeA2CourseSeeder extends PlainsCreeBaseCourseSeeder
{
    protected string $level = 'A2';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Authenticate as the first user for content versioning
        $user = User::first();
        if ($user) {
            Auth::login($user);
        }

        $this->command->info('Creating Plains Cree A2 Course Content...');

        // Initialize languages and template
        $this->initializeLanguagesAndTemplate();

        // Load A2 vocabulary (includes A1 + A2)
        $this->loadVocabulary();

        // Create the complete A2 learning path
        $learningPath = $this->createA2LearningPath();

        // Create units following the curriculum template
        $this->createUnitsFromTemplate($learningPath);

        $this->command->info('Plains Cree A2 course content created successfully!');
    }

    /**
     * Create the A2 learning path
     */
    private function createA2LearningPath(): LearningPath
    {
        return $this->createLearningPath(
            'Plains Cree Elementary (A2) - nēhiyawēwin kiskēyihtamowin',
            'Elementary Plains Cree course building on A1 foundations. Expands vocabulary, introduces more complex grammar, cultural protocols, and conversational skills. Suitable for students who have completed A1 or have basic Plains Cree knowledge.'
        );
    }

    // Level-specific configuration methods
    protected function getDefaultExerciseCount(): int
    {
        return 15; // A2 comprehensive starter package with 15 exercises per lesson
    }

    protected function getVocabularyPerLesson(): int
    {
        return 6; // A2 introduces more vocabulary per lesson
    }

    protected function getDifficultyLevel(): int
    {
        return 2; // A2 difficulty level
    }

    protected function getPassingScore(): int
    {
        return 75; // Slightly higher passing score for A2
    }

    protected function getXpReward(): int
    {
        return 15; // Higher XP rewards for A2 topics
    }

    protected function getExerciseXpReward(): int
    {
        return 8; // Higher XP rewards for A2 exercises
    }

    /**
     * Get topic description based on topic data (A2-specific)
     */
    protected function getTopicDescription(array $topicData): string
    {
        $title = $topicData['title'];

        $descriptions = [
            'Extended Family Relations' => 'Learn extended family terms and complex kinship relationships in Plains Cree culture',
            'Seasonal Activities' => 'Describe seasonal activities, weather, and traditional seasonal practices',
            'Community Roles' => 'Understand community roles, responsibilities, and social structures',
            'Traditional Foods' => 'Learn about traditional Plains Cree foods, preparation methods, and cultural significance',
            'Time and Scheduling' => 'Express time, dates, schedules, and temporal relationships in Plains Cree',
            'Emotions and Feelings' => 'Describe emotions, feelings, and mental states with cultural context',
            'Basic Conversations' => 'Engage in longer conversations about daily life, interests, and experiences',
            'Cultural Protocols' => 'Learn proper cultural protocols, ceremonies, and respectful behavior',
        ];

        return $descriptions[$title] ?? parent::getTopicDescription($topicData);
    }

    /**
     * Get lesson description based on lesson data (A2-specific)
     */
    protected function getLessonDescription(array $lessonData): string
    {
        $type = $lessonData['type'] ?? 'vocabulary';
        $title = $lessonData['title'];

        $typeDescriptions = [
            'vocabulary' => "Expand your Plains Cree vocabulary with {$title} - building on A1 foundations",
            'conversation' => "Practice intermediate Plains Cree conversations with {$title}",
            'pronunciation' => "Refine Plains Cree pronunciation and intonation with {$title}",
            'grammar' => "Learn intermediate Plains Cree grammar structures through {$title}",
            'cultural' => "Deepen your understanding of Plains Cree culture through {$title}",
        ];

        return $typeDescriptions[$type] ?? parent::getLessonDescription($lessonData);
    }

    /**
     * Get topic icon (A2-specific icons)
     */
    protected function getTopicIcon(string $title): string
    {
        $icons = [
            'Extended Family Relations' => '👨‍👩‍👧‍👦',
            'Seasonal Activities' => '🍂',
            'Community Roles' => '🏘️',
            'Traditional Foods' => '🥘',
            'Time and Scheduling' => '⏰',
            'Emotions and Feelings' => '😊',
            'Basic Conversations' => '💬',
            'Cultural Protocols' => '🪶',
        ];

        return $icons[$title] ?? parent::getTopicIcon($title);
    }

    /**
     * Get topic color (A2-specific color scheme)
     */
    protected function getTopicColor(int $unitIndex): string
    {
        $colors = [
            '#4CAF50', // Green - growth and expansion
            '#2196F3', // Blue - communication and flow
            '#FF9800', // Orange - community warmth
            '#9C27B0', // Purple - cultural depth
            '#607D8B', // Blue Grey - stability
        ];

        return $colors[$unitIndex % count($colors)];
    }
}
