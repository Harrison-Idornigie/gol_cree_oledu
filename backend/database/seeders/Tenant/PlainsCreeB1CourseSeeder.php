<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\User;
use App\Models\Tenants\LearningPath;
use Illuminate\Support\Facades\Auth;

class PlainsCreeB1CourseSeeder extends PlainsCreeBaseCourseSeeder
{
    protected string $level = 'B1';

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

        $this->command->info('Creating Plains Cree B1 Course Content...');

        // Initialize languages and template
        $this->initializeLanguagesAndTemplate();

        // Load B1 vocabulary (includes A1 + A2 + B1)
        $this->loadVocabulary();

        // Create the complete B1 learning path
        $learningPath = $this->createB1LearningPath();

        // Create units following the curriculum template
        $this->createUnitsFromTemplate($learningPath);

        $this->command->info('Plains Cree B1 course content created successfully!');
    }

    /**
     * Create the B1 learning path
     */
    private function createB1LearningPath(): LearningPath
    {
        return $this->createLearningPath(
            'Plains Cree Intermediate (B1) - nēhiyawēwin kiskēyihtamowin',
            'Intermediate Plains Cree course for conversational fluency. Focus on storytelling, complex grammar, cultural teachings, and independent communication. For students with solid A1-A2 foundation ready for deeper cultural and linguistic engagement.'
        );
    }

    // Level-specific configuration methods
    protected function getDefaultExerciseCount(): int
    {
        return 15; // B1 comprehensive starter package with 15 exercises per lesson
    }

    protected function getVocabularyPerLesson(): int
    {
        return 5; // B1 focuses on depth over quantity
    }

    protected function getDifficultyLevel(): int
    {
        return 3; // B1 difficulty level
    }

    protected function getPassingScore(): int
    {
        return 80; // Higher passing score for B1
    }

    protected function getXpReward(): int
    {
        return 20; // Higher XP rewards for B1 topics
    }

    protected function getExerciseXpReward(): int
    {
        return 10; // Higher XP rewards for B1 exercises
    }

    /**
     * Get topic description based on topic data (B1-specific)
     */
    protected function getTopicDescription(array $topicData): string
    {
        $title = $topicData['title'];

        $descriptions = [
            'Traditional Stories' => 'Learn and tell traditional Plains Cree stories with proper narrative structure',
            'Complex Grammar' => 'Master complex grammatical structures including verb conjugations and sentence patterns',
            'Cultural Teachings' => 'Understand deep cultural teachings, values, and traditional knowledge systems',
            'Land-Based Learning' => 'Connect language learning with traditional land-based activities and knowledge',
            'Ceremonial Language' => 'Learn appropriate language for ceremonies, prayers, and sacred contexts',
            'Community Discussions' => 'Participate in community discussions about current issues and traditional topics',
            'Elder Teachings' => 'Understand and discuss teachings from elders and knowledge keepers',
            'Seasonal Ceremonies' => 'Learn about seasonal ceremonies, their significance, and appropriate language',
        ];

        return $descriptions[$title] ?? parent::getTopicDescription($topicData);
    }

    /**
     * Get lesson description based on lesson data (B1-specific)
     */
    protected function getLessonDescription(array $lessonData): string
    {
        $type = $lessonData['type'] ?? 'vocabulary';
        $title = $lessonData['title'];

        $typeDescriptions = [
            'vocabulary' => "Master intermediate Plains Cree vocabulary with {$title} - focus on cultural depth",
            'conversation' => "Engage in complex Plains Cree conversations about {$title}",
            'pronunciation' => "Perfect Plains Cree pronunciation and natural speech patterns with {$title}",
            'grammar' => "Master intermediate Plains Cree grammar through {$title}",
            'cultural' => "Explore traditional Plains Cree knowledge through {$title}",
            'storytelling' => "Learn traditional storytelling techniques through {$title}",
        ];

        return $typeDescriptions[$type] ?? parent::getLessonDescription($lessonData);
    }

    /**
     * Get topic icon (B1-specific icons)
     */
    protected function getTopicIcon(string $title): string
    {
        $icons = [
            'Traditional Stories' => '📚',
            'Complex Grammar' => '🔤',
            'Cultural Teachings' => '🪶',
            'Land-Based Learning' => '🌲',
            'Ceremonial Language' => '🕊️',
            'Community Discussions' => '🗣️',
            'Elder Teachings' => '👴',
            'Seasonal Ceremonies' => '🌙',
        ];

        return $icons[$title] ?? parent::getTopicIcon($title);
    }

    /**
     * Get topic color (B1-specific color scheme)
     */
    protected function getTopicColor(int $unitIndex): string
    {
        $colors = [
            '#8BC34A', // Light Green - growth and wisdom
            '#00BCD4', // Cyan - flowing knowledge
            '#FF5722', // Deep Orange - sacred fire
            '#795548', // Brown - earth connection
            '#9E9E9E', // Grey - elder wisdom
            '#3F51B5', // Indigo - deep understanding
        ];

        return $colors[$unitIndex % count($colors)];
    }
}
