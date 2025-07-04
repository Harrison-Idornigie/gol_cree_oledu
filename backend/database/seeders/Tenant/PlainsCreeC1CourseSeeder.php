<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\User;
use App\Models\Tenants\LearningPath;
use Illuminate\Support\Facades\Auth;

class PlainsCreeC1CourseSeeder extends PlainsCreeBaseCourseSeeder
{
    protected string $level = 'C1';

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

        $this->command->info('Creating Plains Cree C1 Course Content...');

        // Initialize languages and template
        $this->initializeLanguagesAndTemplate();

        // Load C1 vocabulary (includes A1 through C1)
        $this->loadVocabulary();

        // Create the complete C1 learning path
        $learningPath = $this->createC1LearningPath();

        // Create units following the curriculum template
        $this->createUnitsFromTemplate($learningPath);

        $this->command->info('Plains Cree C1 course content created successfully!');
    }

    /**
     * Create the C1 learning path
     */
    private function createC1LearningPath(): LearningPath
    {
        return $this->createLearningPath(
            'Plains Cree Advanced (C1) - nēhiyawēwin kiskēyihtamowin',
            'Advanced Plains Cree course for near-native fluency. Focus on elder wisdom, sacred knowledge, traditional teachings, and cultural transmission. For advanced students ready to become cultural knowledge carriers and language teachers.'
        );
    }

    // Level-specific configuration methods
    protected function getDefaultExerciseCount(): int
    {
        return 15; // C1 comprehensive starter package with 15 exercises per lesson
    }

    protected function getVocabularyPerLesson(): int
    {
        return 3; // C1 focuses on specialized, high-level vocabulary
    }

    protected function getDifficultyLevel(): int
    {
        return 5; // C1 difficulty level
    }

    protected function getPassingScore(): int
    {
        return 90; // High passing score for C1
    }

    protected function getXpReward(): int
    {
        return 30; // High XP rewards for C1 topics
    }

    protected function getExerciseXpReward(): int
    {
        return 15; // High XP rewards for C1 exercises
    }

    /**
     * Get topic description based on topic data (C1-specific)
     */
    protected function getTopicDescription(array $topicData): string
    {
        $title = $topicData['title'];

        $descriptions = [
            'Elder Wisdom' => 'Learn from elder teachings, traditional knowledge systems, and ancestral wisdom',
            'Sacred Knowledge' => 'Understand sacred knowledge, spiritual concepts, and ceremonial responsibilities',
            'Cultural Transmission' => 'Master the art of cultural transmission, teaching methods, and knowledge sharing',
            'Traditional Oratory' => 'Develop skills in traditional oratory, formal speaking, and ceremonial address',
            'Ancestral Teachings' => 'Explore deep ancestral teachings, creation stories, and foundational knowledge',
            'Spiritual Concepts' => 'Understand complex spiritual concepts, metaphysical ideas, and sacred relationships',
            'Knowledge Keeping' => 'Learn the responsibilities and methods of traditional knowledge keeping',
            'Cultural Mentorship' => 'Develop skills for cultural mentorship, guidance, and community leadership',
        ];

        return $descriptions[$title] ?? parent::getTopicDescription($topicData);
    }

    /**
     * Get lesson description based on lesson data (C1-specific)
     */
    protected function getLessonDescription(array $lessonData): string
    {
        $type = $lessonData['type'] ?? 'vocabulary';
        $title = $lessonData['title'];

        $typeDescriptions = [
            'vocabulary' => "Master advanced Plains Cree vocabulary with {$title} - specialized cultural and spiritual terms",
            'conversation' => "Engage in elder-level Plains Cree discourse about {$title}",
            'pronunciation' => "Perfect ceremonial Plains Cree pronunciation and sacred speech patterns with {$title}",
            'grammar' => "Master the most complex Plains Cree grammar structures through {$title}",
            'cultural' => "Explore the deepest levels of Plains Cree cultural knowledge through {$title}",
            'teaching' => "Learn traditional teaching methods through {$title}",
        ];

        return $typeDescriptions[$type] ?? parent::getLessonDescription($lessonData);
    }

    /**
     * Get topic icon (C1-specific icons)
     */
    protected function getTopicIcon(string $title): string
    {
        $icons = [
            'Elder Wisdom' => '👴🏽',
            'Sacred Knowledge' => '🔮',
            'Cultural Transmission' => '📜',
            'Traditional Oratory' => '🎤',
            'Ancestral Teachings' => '🌟',
            'Spiritual Concepts' => '✨',
            'Knowledge Keeping' => '📚',
            'Cultural Mentorship' => '🤲',
        ];

        return $icons[$title] ?? parent::getTopicIcon($title);
    }

    /**
     * Get topic color (C1-specific color scheme)
     */
    protected function getTopicColor(int $unitIndex): string
    {
        $colors = [
            '#4A148C', // Deep Purple - spiritual depth
            '#BF360C', // Deep Orange - sacred fire
            '#1A237E', // Indigo - deep wisdom
            '#263238', // Blue Grey - ancient knowledge
            '#3E2723', // Brown - earth connection
            '#0D47A1', // Blue - sky connection
        ];

        return $colors[$unitIndex % count($colors)];
    }
}
