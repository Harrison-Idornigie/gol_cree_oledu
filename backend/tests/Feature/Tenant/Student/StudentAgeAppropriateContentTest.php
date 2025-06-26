<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Unit;
use App\Models\Tenants\Topic;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

/**
 * Age-Appropriate Content API Tests
 * 
 * Tests age-appropriate content delivery for different learning paths (kids vs teen/adult)
 * and ensures proper content filtering based on age groups for Plains Cree curriculum.
 */
class StudentAgeAppropriateContentTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $plainsCreeLanguage;
    protected array $testLearningPaths = [];
    protected array $testContent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);

        // Create users
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeam();

        // Create test environment
        $this->createTestLanguage();
        $this->createAgeAppropriateContent();
    }

    protected function createTestLanguage(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $this->plainsCreeLanguage = Language::create([
                'name' => 'Plains Cree',
                'code' => 'crk',
                'native_name' => 'nēhiyawēwin',
                'is_active' => true,
                'metadata' => [
                    'writing_system' => 'syllabics',
                    'has_audio' => true,
                    'cultural_context' => 'indigenous',
                    'age_groups' => ['kids', 'teen_adult']
                ]
            ]);
        });
    }

    protected function createAgeAppropriateContent(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $ageGroups = ['kids', 'teen_adult'];
            $levels = ['A1', 'A2'];

            foreach ($ageGroups as $ageGroup) {
                foreach ($levels as $level) {
                    // Create learning path for each age group and level
                    $learningPath = LearningPath::create([
                        'title' => "Plains Cree {$level} for {$ageGroup}",
                        'slug' => "plains-cree-{$level}-{$ageGroup}-" . Str::random(8),
                        'description' => $this->getAgeAppropriateDescription($level, $ageGroup),
                        'language_id' => $this->plainsCreeLanguage->id,
                        'target_level' => $level,
                        'status' => 'published',
                        'metadata' => [
                            'age_group' => $ageGroup,
                            'proficiency_level' => $level,
                            'content_style' => $this->getContentStyle($ageGroup),
                            'lesson_duration' => $this->getLessonDuration($ageGroup),
                            'gamification_level' => $this->getGamificationLevel($ageGroup),
                            'cultural_sensitivity' => $this->getCulturalSensitivity($ageGroup),
                            'vocabulary_complexity' => $this->getVocabularyComplexity($ageGroup, $level)
                        ],
                        'created_by' => $this->teamUser->id,
                    ]);

                    $this->testLearningPaths["{$level}_{$ageGroup}"] = $learningPath;

                    // Create age-appropriate content structure
                    $this->createAgeAppropriateUnit($learningPath, $level, $ageGroup);
                }
            }
        });
    }

    protected function createAgeAppropriateUnit(LearningPath $learningPath, string $level, string $ageGroup): void
    {
        $unit = Unit::create([
            'learning_path_id' => $learningPath->id,
            'title' => $this->getAgeAppropriateUnitTitle($level, $ageGroup),
            'description' => $this->getAgeAppropriateUnitDescription($level, $ageGroup),
            'order' => 1,
            'status' => 'published',
            'metadata' => [
                'age_group' => $ageGroup,
                'proficiency_level' => $level,
                'content_themes' => $this->getContentThemes($ageGroup),
                'interaction_style' => $this->getInteractionStyle($ageGroup),
                'visual_design' => $this->getVisualDesign($ageGroup),
                'estimated_duration' => $this->getUnitDuration($ageGroup)
            ],
            'created_by' => $this->teamUser->id,
        ]);

        $this->testContent["{$level}_{$ageGroup}"]['unit'] = $unit;

        // Create age-appropriate topic
        $this->createAgeAppropriateTopic($unit, $level, $ageGroup);
    }

    protected function createAgeAppropriateTopic(Unit $unit, string $level, string $ageGroup): void
    {
        $topic = Topic::create([
            'unit_id' => $unit->id,
            'title' => $this->getAgeAppropriateTopicTitle($level, $ageGroup),
            'description' => $this->getAgeAppropriateTopicDescription($level, $ageGroup),
            'order' => 1,
            'status' => 'published',
            'metadata' => [
                'age_group' => $ageGroup,
                'proficiency_level' => $level,
                'learning_objectives' => $this->getLearningObjectives($ageGroup, $level),
                'engagement_strategies' => $this->getEngagementStrategies($ageGroup),
                'assessment_style' => $this->getAssessmentStyle($ageGroup)
            ],
            'created_by' => $this->teamUser->id,
        ]);

        $this->testContent["{$level}_{$ageGroup}"]['topic'] = $topic;

        // Create age-appropriate lesson
        $this->createAgeAppropriateLesson($topic, $level, $ageGroup);
    }

    protected function createAgeAppropriateLesson(Topic $topic, string $level, string $ageGroup): void
    {
        $lesson = Lesson::create([
            'topic_id' => $topic->id,
            'title' => $this->getAgeAppropriateLessonTitle($level, $ageGroup),
            'description' => $this->getAgeAppropriateLessonDescription($level, $ageGroup),
            'order' => 1,
            'status' => 'published',
            'metadata' => [
                'age_group' => $ageGroup,
                'proficiency_level' => $level,
                'content_delivery' => $this->getContentDelivery($ageGroup),
                'interaction_frequency' => $this->getInteractionFrequency($ageGroup),
                'feedback_style' => $this->getFeedbackStyle($ageGroup),
                'progress_indicators' => $this->getProgressIndicators($ageGroup)
            ],
            'created_by' => $this->teamUser->id,
        ]);

        $this->testContent["{$level}_{$ageGroup}"]['lesson'] = $lesson;

        // Create age-appropriate exercise
        $this->createAgeAppropriateExercise($lesson, $level, $ageGroup);
    }

    protected function createAgeAppropriateExercise(Lesson $lesson, string $level, string $ageGroup): void
    {
        $exercise = Exercise::create([
            'lesson_id' => $lesson->id,
            'title' => $this->getAgeAppropriateExerciseTitle($level, $ageGroup),
            'type' => $ageGroup === 'kids' ? 'drag_and_drop' : 'multiple_choice',
            'order' => 1,
            'status' => 'published',
            'content' => [
                'instruction' => $this->getAgeAppropriateInstruction($ageGroup),
                'difficulty_adaptation' => $this->getDifficultyAdaptation($ageGroup),
                'reward_system' => $this->getRewardSystem($ageGroup),
                'time_limits' => $this->getTimeLimits($ageGroup),
                'hint_system' => $this->getHintSystem($ageGroup)
            ],
            'metadata' => [
                'age_group' => $ageGroup,
                'proficiency_level' => $level,
                'cognitive_load' => $this->getCognitiveLoad($ageGroup),
                'attention_span' => $this->getAttentionSpan($ageGroup),
                'motivation_factors' => $this->getMotivationFactors($ageGroup)
            ],
            'created_by' => $this->teamUser->id,
        ]);

        $this->testContent["{$level}_{$ageGroup}"]['exercise'] = $exercise;
    }

    // Age-appropriate content configuration methods
    protected function getAgeAppropriateDescription(string $level, string $ageGroup): string
    {
        if ($ageGroup === 'kids') {
            return "Fun and interactive Plains Cree {$level} course designed for young learners with games, stories, and colorful activities!";
        }
        return "Comprehensive Plains Cree {$level} course for teenagers and adults focusing on practical communication and cultural understanding.";
    }

    protected function getContentStyle(string $ageGroup): string
    {
        return $ageGroup === 'kids' ? 'playful_interactive' : 'structured_practical';
    }

    protected function getLessonDuration(string $ageGroup): int
    {
        return $ageGroup === 'kids' ? 15 : 30; // minutes
    }

    protected function getGamificationLevel(string $ageGroup): string
    {
        return $ageGroup === 'kids' ? 'high' : 'moderate';
    }

    protected function getCulturalSensitivity(string $ageGroup): string
    {
        return $ageGroup === 'kids' ? 'age_appropriate_stories' : 'comprehensive_cultural_context';
    }

    protected function getVocabularyComplexity(string $ageGroup, string $level): string
    {
        if ($ageGroup === 'kids') {
            return $level === 'A1' ? 'simple_concrete' : 'expanded_concrete';
        }
        return $level === 'A1' ? 'practical_everyday' : 'comprehensive_contextual';
    }

    protected function getAgeAppropriateUnitTitle(string $level, string $ageGroup): string
    {
        if ($ageGroup === 'kids') {
            return "Unit 1: Let's Learn Plains Cree! ({$level})";
        }
        return "Unit 1: Plains Cree Fundamentals ({$level})";
    }

    protected function getAgeAppropriateUnitDescription(string $level, string $ageGroup): string
    {
        if ($ageGroup === 'kids') {
            return "Join us on a fun adventure learning Plains Cree words and sounds through games and stories!";
        }
        return "Build a solid foundation in Plains Cree language with practical vocabulary and cultural insights.";
    }

    protected function getContentThemes(string $ageGroup): array
    {
        if ($ageGroup === 'kids') {
            return ['animals', 'family', 'colors', 'numbers', 'nature', 'games'];
        }
        return ['daily_life', 'community', 'work', 'relationships', 'traditions', 'current_events'];
    }

    protected function getInteractionStyle(string $ageGroup): string
    {
        return $ageGroup === 'kids' ? 'game_based' : 'discussion_based';
    }

    protected function getVisualDesign(string $ageGroup): string
    {
        return $ageGroup === 'kids' ? 'colorful_animated' : 'clean_professional';
    }

    protected function getUnitDuration(string $ageGroup): int
    {
        return $ageGroup === 'kids' ? 60 : 120; // minutes
    }

    protected function getAgeAppropriateTopicTitle(string $level, string $ageGroup): string
    {
        if ($ageGroup === 'kids') {
            return "Topic 1: Hello Friends! - tanisi";
        }
        return "Topic 1: Greetings and Introductions";
    }

    protected function getAgeAppropriateTopicDescription(string $level, string $ageGroup): string
    {
        if ($ageGroup === 'kids') {
            return "Learn how to say hello and make friends in Plains Cree!";
        }
        return "Master formal and informal greetings in Plains Cree for various social contexts.";
    }

    protected function getLearningObjectives(string $ageGroup, string $level): array
    {
        if ($ageGroup === 'kids') {
            return [
                'Recognize basic Plains Cree greetings',
                'Use simple syllabics symbols',
                'Enjoy learning through play',
                'Build confidence in speaking'
            ];
        }
        return [
            'Master formal and informal greetings',
            'Understand cultural protocols',
            'Read and write syllabics accurately',
            'Apply greetings in real situations'
        ];
    }

    protected function getEngagementStrategies(string $ageGroup): array
    {
        if ($ageGroup === 'kids') {
            return ['songs', 'games', 'stories', 'animations', 'rewards', 'characters'];
        }
        return ['discussions', 'role_plays', 'cultural_contexts', 'practical_applications', 'peer_interaction'];
    }

    protected function getAssessmentStyle(string $ageGroup): string
    {
        return $ageGroup === 'kids' ? 'play_based_observation' : 'formal_assessment';
    }

    // Helper methods for creating users
    protected function createTenantStudent(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge([
                'email' => 'student@test.com',
                'membership' => 'student',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    protected function createTenantTeam(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge([
                'email' => 'team@test.com',
                'membership' => 'team',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    // Additional helper methods
    protected function getAgeAppropriateLessonTitle(string $level, string $ageGroup): string
    {
        if ($ageGroup === 'kids') {
            return "Lesson 1: Say Hello Like a Friend!";
        }
        return "Lesson 1: Proper Greetings in Plains Cree";
    }

    protected function getAgeAppropriateLessonDescription(string $level, string $ageGroup): string
    {
        if ($ageGroup === 'kids') {
            return "Learn the magic word 'tanisi' and how to greet your new Plains Cree friends!";
        }
        return "Learn appropriate greetings for different social situations in Plains Cree culture.";
    }

    protected function getContentDelivery(string $ageGroup): string
    {
        return $ageGroup === 'kids' ? 'story_based' : 'explanation_based';
    }

    protected function getInteractionFrequency(string $ageGroup): string
    {
        return $ageGroup === 'kids' ? 'every_30_seconds' : 'every_2_minutes';
    }

    protected function getFeedbackStyle(string $ageGroup): string
    {
        return $ageGroup === 'kids' ? 'encouraging_animated' : 'constructive_detailed';
    }

    protected function getProgressIndicators(string $ageGroup): array
    {
        if ($ageGroup === 'kids') {
            return ['stars', 'badges', 'progress_bar', 'character_growth'];
        }
        return ['percentage', 'skill_levels', 'competency_markers', 'achievement_unlocks'];
    }

    protected function getAgeAppropriateExerciseTitle(string $level, string $ageGroup): string
    {
        if ($ageGroup === 'kids') {
            return "Match the Greeting Game!";
        }
        return "Greeting Recognition Exercise";
    }

    protected function getAgeAppropriateInstruction(string $ageGroup): string
    {
        if ($ageGroup === 'kids') {
            return "Help the characters find their matching greetings by dragging them together!";
        }
        return "Select the correct Plains Cree greeting for each situation described below.";
    }

    protected function getDifficultyAdaptation(string $ageGroup): string
    {
        return $ageGroup === 'kids' ? 'automatic_simplification' : 'user_controlled';
    }

    protected function getRewardSystem(string $ageGroup): array
    {
        if ($ageGroup === 'kids') {
            return ['stars', 'stickers', 'animations', 'sound_effects', 'character_reactions'];
        }
        return ['points', 'achievements', 'progress_tracking', 'skill_badges'];
    }

    protected function getTimeLimits(string $ageGroup): ?int
    {
        return $ageGroup === 'kids' ? null : 120; // seconds, no time pressure for kids
    }

    protected function getHintSystem(string $ageGroup): array
    {
        if ($ageGroup === 'kids') {
            return ['visual_hints', 'audio_prompts', 'character_guidance', 'unlimited_hints'];
        }
        return ['text_hints', 'limited_hints', 'progressive_disclosure'];
    }

    protected function getCognitiveLoad(string $ageGroup): string
    {
        return $ageGroup === 'kids' ? 'low' : 'moderate';
    }

    protected function getAttentionSpan(string $ageGroup): int
    {
        return $ageGroup === 'kids' ? 5 : 15; // minutes
    }

    protected function getMotivationFactors(string $ageGroup): array
    {
        if ($ageGroup === 'kids') {
            return ['fun', 'discovery', 'achievement', 'social_approval', 'immediate_rewards'];
        }
        return ['mastery', 'practical_application', 'cultural_connection', 'personal_growth', 'long_term_goals'];
    }

    /** @test */
    public function student_can_access_age_appropriate_learning_paths()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get learning paths filtered by age group
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths?age_group=kids");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'metadata' => [
                                'age_group',
                                'content_style',
                                'lesson_duration',
                                'gamification_level'
                            ]
                        ]
                    ]
                ]
            ]);

        $learningPaths = $response->json('data.data');
        foreach ($learningPaths as $path) {
            $this->assertEquals('kids', $path['metadata']['age_group']);
            $this->assertEquals('playful_interactive', $path['metadata']['content_style']);
            $this->assertEquals(15, $path['metadata']['lesson_duration']);
            $this->assertEquals('high', $path['metadata']['gamification_level']);
        }
    }

    /** @test */
    public function student_can_access_teen_adult_learning_paths()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get learning paths for teen/adult
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths?age_group=teen_adult");

        $response->assertStatus(200);

        $learningPaths = $response->json('data.data');
        foreach ($learningPaths as $path) {
            $this->assertEquals('teen_adult', $path['metadata']['age_group']);
            $this->assertEquals('structured_practical', $path['metadata']['content_style']);
            $this->assertEquals(30, $path['metadata']['lesson_duration']);
            $this->assertEquals('moderate', $path['metadata']['gamification_level']);
        }
    }

    /** @test */
    public function student_can_access_age_appropriate_unit_content()
    {
        // Enroll student in kids learning path
        $this->enrollStudentInLearningPath('A1_kids');

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A1_kids'];

        // API call to get units
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}/units");

        $response->assertStatus(200);

        $units = $response->json('data');
        foreach ($units as $unit) {
            $this->assertEquals('kids', $unit['metadata']['age_group']);
            $this->assertEquals('game_based', $unit['metadata']['interaction_style']);
            $this->assertEquals('colorful_animated', $unit['metadata']['visual_design']);
            $this->assertEquals(60, $unit['metadata']['estimated_duration']);
            $this->assertContains('animals', $unit['metadata']['content_themes']);
        }
    }

    // Helper method for enrollment
    private function enrollStudentInLearningPath(string $pathKey): void
    {
        $this->runInTenantContext($this->tenant, function () use ($pathKey) {
            \Illuminate\Support\Facades\DB::table('user_learning_paths')->insert([
                'id' => Str::uuid(),
                'user_id' => $this->studentUser->id,
                'learning_path_id' => $this->testLearningPaths[$pathKey]->id,
                'enrolled_at' => now(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
