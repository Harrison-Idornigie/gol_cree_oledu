<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\User;
use App\Models\Tenants\LearningPath;
use Illuminate\Support\Facades\Auth;

class PlainsCreeB2CourseSeeder extends PlainsCreeBaseCourseSeeder
{
    protected string $level = 'B2';

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

        $this->command->info('Creating Plains Cree B2 Course Content...');

        // Initialize languages and template
        $this->initializeLanguagesAndTemplate();

        // Load B2 vocabulary (includes A1 + A2 + B1 + B2)
        $this->loadVocabulary();

        // Create the complete B2 learning path
        $learningPath = $this->createB2LearningPath();

        // Create units following the curriculum template
        $this->createUnitsFromTemplate($learningPath);

        $this->command->info('Plains Cree B2 course content created successfully!');
    }

    /**
     * Create the B2 learning path
     */
    private function createB2LearningPath(): LearningPath
    {
        return $this->createLearningPath(
            'Plains Cree Upper-Intermediate (B2) - nēhiyawēwin kiskēyihtamowin',
            'Upper-intermediate Plains Cree course for complex communication. Focus on abstract concepts, cultural leadership, traditional governance, and sophisticated discourse. For students ready to engage with complex cultural and philosophical topics.'
        );
    }

    // Level-specific configuration methods
    protected function getDefaultExerciseCount(): int
    {
        return 8; // B2 has sophisticated exercises
    }

    protected function getVocabularyPerLesson(): int
    {
        return 4; // B2 focuses on sophisticated vocabulary
    }

    protected function getDifficultyLevel(): int
    {
        return 4; // B2 difficulty level
    }

    protected function getPassingScore(): int
    {
        return 85; // Higher passing score for B2
    }

    protected function getXpReward(): int
    {
        return 25; // Higher XP rewards for B2 topics
    }

    protected function getExerciseXpReward(): int
    {
        return 12; // Higher XP rewards for B2 exercises
    }

    /**
     * Get topic description based on topic data (B2-specific)
     */
    protected function getTopicDescription(array $topicData): string
    {
        $title = $topicData['title'];

        $descriptions = [
            'Traditional Governance' => 'Understand traditional Plains Cree governance systems, leadership roles, and decision-making processes',
            'Philosophical Concepts' => 'Explore complex philosophical concepts in Plains Cree worldview and traditional knowledge',
            'Advanced Storytelling' => 'Master advanced storytelling techniques, including sacred stories and teaching narratives',
            'Cultural Leadership' => 'Learn about cultural leadership roles, responsibilities, and traditional authority structures',
            'Kinship Systems' => 'Master complex kinship terminology and relationship structures in Plains Cree society',
            'Ceremonial Protocols' => 'Understand detailed ceremonial protocols, proper conduct, and sacred responsibilities',
            'Traditional Medicine' => 'Learn traditional medicine concepts, plant knowledge, and healing practices (cultural context)',
            'Community Cooperation' => 'Explore concepts of community cooperation, collective decision-making, and mutual support',
        ];

        return $descriptions[$title] ?? parent::getTopicDescription($topicData);
    }

    /**
     * Get lesson description based on lesson data (B2-specific)
     */
    protected function getLessonDescription(array $lessonData): string
    {
        $type = $lessonData['type'] ?? 'vocabulary';
        $title = $lessonData['title'];

        $typeDescriptions = [
            'vocabulary' => "Master sophisticated Plains Cree vocabulary with {$title} - abstract and cultural concepts",
            'conversation' => "Engage in sophisticated Plains Cree discourse about {$title}",
            'pronunciation' => "Perfect nuanced Plains Cree pronunciation and formal speech patterns with {$title}",
            'grammar' => "Master advanced Plains Cree grammar structures through {$title}",
            'cultural' => "Explore deep cultural knowledge and philosophy through {$title}",
            'leadership' => "Learn cultural leadership concepts through {$title}",
        ];

        return $typeDescriptions[$type] ?? parent::getLessonDescription($lessonData);
    }

    /**
     * Get topic icon (B2-specific icons)
     */
    protected function getTopicIcon(string $title): string
    {
        $icons = [
            'Traditional Governance' => '⚖️',
            'Philosophical Concepts' => '🧠',
            'Advanced Storytelling' => '📖',
            'Cultural Leadership' => '👑',
            'Kinship Systems' => '🌐',
            'Ceremonial Protocols' => '🕯️',
            'Traditional Medicine' => '🌿',
            'Community Cooperation' => '🤝',
        ];

        return $icons[$title] ?? parent::getTopicIcon($title);
    }

    /**
     * Get topic color (B2-specific color scheme)
     */
    protected function getTopicColor(int $unitIndex): string
    {
        $colors = [
            '#673AB7', // Deep Purple - wisdom and spirituality
            '#E91E63', // Pink - life force and vitality
            '#FF6F00', // Amber - sacred fire and transformation
            '#4E342E', // Brown - earth connection and grounding
            '#37474F', // Blue Grey - balance and stability
            '#1B5E20', // Dark Green - deep growth and knowledge
        ];

        return $colors[$unitIndex % count($colors)];
    }
}
