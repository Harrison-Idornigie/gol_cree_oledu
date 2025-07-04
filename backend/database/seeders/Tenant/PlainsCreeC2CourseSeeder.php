<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\User;
use App\Models\Tenants\LearningPath;
use Illuminate\Support\Facades\Auth;

class PlainsCreeC2CourseSeeder extends PlainsCreeBaseCourseSeeder
{
    protected string $level = 'C2';

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

        $this->command->info('Creating Plains Cree C2 Course Content...');

        // Initialize languages and template
        $this->initializeLanguagesAndTemplate();

        // Load C2 vocabulary (includes A1 through C2)
        $this->loadVocabulary();

        // Create the complete C2 learning path
        $learningPath = $this->createC2LearningPath();

        // Create units following the curriculum template
        $this->createUnitsFromTemplate($learningPath);

        $this->command->info('Plains Cree C2 course content created successfully!');
    }

    /**
     * Create the C2 learning path
     */
    private function createC2LearningPath(): LearningPath
    {
        return $this->createLearningPath(
            'Plains Cree Mastery (C2) - nēhiyawēwin kiskēyihtamowin',
            'Mastery-level Plains Cree course for native-like fluency. Focus on ceremonial language, sacred oratory, traditional governance, and becoming a cultural knowledge keeper. For advanced practitioners ready to serve as language and cultural leaders in their communities.'
        );
    }

    // Level-specific configuration methods
    protected function getDefaultExerciseCount(): int
    {
        return 15; // C2 comprehensive starter package with 15 exercises per lesson
    }

    protected function getVocabularyPerLesson(): int
    {
        return 2; // C2 focuses on highly specialized vocabulary
    }

    protected function getDifficultyLevel(): int
    {
        return 6; // C2 difficulty level (mastery)
    }

    protected function getPassingScore(): int
    {
        return 95; // Very high passing score for C2
    }

    protected function getXpReward(): int
    {
        return 40; // Highest XP rewards for C2 topics
    }

    protected function getExerciseXpReward(): int
    {
        return 20; // Highest XP rewards for C2 exercises
    }

    /**
     * Get topic description based on topic data (C2-specific)
     */
    protected function getTopicDescription(array $topicData): string
    {
        $title = $topicData['title'];

        $descriptions = [
            'Ceremonial Oratory' => 'Master ceremonial oratory, sacred speech, and formal ceremonial language protocols',
            'Sacred Responsibilities' => 'Understand the deepest sacred responsibilities, ceremonial duties, and spiritual obligations',
            'Traditional Governance' => 'Master traditional governance language, formal protocols, and leadership communication',
            'Knowledge Transmission' => 'Perfect the art of knowledge transmission, teaching sacred knowledge, and cultural preservation',
            'Spiritual Leadership' => 'Develop spiritual leadership language, ceremonial guidance, and sacred communication',
            'Cultural Preservation' => 'Master language for cultural preservation, documentation, and intergenerational transmission',
            'Ceremonial Protocols' => 'Perfect understanding of all ceremonial protocols, sacred procedures, and ritual language',
            'Elder Responsibilities' => 'Understand elder responsibilities, community guidance, and traditional authority language',
        ];

        return $descriptions[$title] ?? parent::getTopicDescription($topicData);
    }

    /**
     * Get lesson description based on lesson data (C2-specific)
     */
    protected function getLessonDescription(array $lessonData): string
    {
        $type = $lessonData['type'] ?? 'vocabulary';
        $title = $lessonData['title'];

        $typeDescriptions = [
            'vocabulary' => "Master the most sacred Plains Cree vocabulary with {$title} - ceremonial and spiritual mastery",
            'conversation' => "Engage in ceremonial-level Plains Cree discourse about {$title}",
            'pronunciation' => "Perfect sacred Plains Cree pronunciation and ceremonial speech patterns with {$title}",
            'grammar' => "Master the most complex and sacred Plains Cree grammar through {$title}",
            'cultural' => "Achieve mastery of Plains Cree cultural knowledge through {$title}",
            'ceremonial' => "Master ceremonial language and sacred communication through {$title}",
        ];

        return $typeDescriptions[$type] ?? parent::getLessonDescription($lessonData);
    }

    /**
     * Get topic icon (C2-specific icons)
     */
    protected function getTopicIcon(string $title): string
    {
        $icons = [
            'Ceremonial Oratory' => '🎭',
            'Sacred Responsibilities' => '⚡',
            'Traditional Governance' => '👑',
            'Knowledge Transmission' => '📿',
            'Spiritual Leadership' => '🌟',
            'Cultural Preservation' => '🏛️',
            'Ceremonial Protocols' => '🕊️',
            'Elder Responsibilities' => '🦅',
        ];

        return $icons[$title] ?? parent::getTopicIcon($title);
    }

    /**
     * Get topic color (C2-specific color scheme)
     */
    protected function getTopicColor(int $unitIndex): string
    {
        $colors = [
            '#000000', // Black - sacred depth and mystery
            '#FFD700', // Gold - sacred wisdom and enlightenment
            '#8B0000', // Dark Red - sacred fire and power
            '#191970', // Midnight Blue - deep spiritual knowledge
            '#2F4F4F', // Dark Slate Grey - ancient wisdom
            '#800080', // Purple - spiritual mastery
        ];

        return $colors[$unitIndex % count($colors)];
    }
}
