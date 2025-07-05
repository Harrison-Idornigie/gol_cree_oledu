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
use App\Models\Tenants\UserProgress;
use App\Models\Tenants\UserLearningPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

/**
 * Unit and Topic API Tests with Sequential Learning
 * 
 * Tests sequential learning enforcement, syllabics content delivery,
 * and proper access controls for Plains Cree curriculum structure.
 */
class StudentUnitTopicSequentialTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $plainsCreeLanguage;
    protected LearningPath $learningPath;
    protected array $testUnits = [];
    protected array $testTopics = [];

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
        $this->createTestLearningPath();
        $this->createTestUnitsAndTopics();
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
                    'cultural_context' => 'indigenous'
                ]
            ]);
        });
    }

    protected function createTestLearningPath(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $this->learningPath = LearningPath::create([
                'title' => 'Plains Cree A1 - Sequential Learning Test',
                'slug' => 'plains-cree-a1-sequential-' . Str::random(8),
                'description' => 'Plains Cree A1 course with sequential learning',
                'language_id' => $this->plainsCreeLanguage->id,
                'target_level' => 'A1',
                'status' => 'published',
                'metadata' => [
                    'sequential_learning' => true,
                    'proficiency_level' => 'A1',
                    'syllabics_instruction' => true
                ],
                'created_by' => $this->teamUser->id,
            ]);
        });
    }

    protected function createTestUnitsAndTopics(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            // Create 3 units in sequence
            for ($i = 1; $i <= 3; $i++) {
                $unit = Unit::create([
                    'learning_path_id' => $this->learningPath->id,
                    'title' => "Unit {$i}: Plains Cree Basics",
                    'description' => "Unit {$i} description with syllabics content",
                    'order' => $i,
                    'status' => 'published',
                    'metadata' => [
                        'proficiency_level' => 'A1',
                        'syllabics_focus' => true,
                        'cultural_context' => "Traditional Plains Cree knowledge - Unit {$i}",
                        'sequential_requirement' => $i > 1 ? "unit_" . ($i - 1) : null,
                        'estimated_duration' => 120, // minutes
                        'xp_reward' => 100
                    ],
                    'created_by' => $this->teamUser->id,
                ]);

                $this->testUnits[$i] = $unit;

                // Create 2 topics per unit
                for ($j = 1; $j <= 2; $j++) {
                    $topic = Topic::create([
                        'unit_id' => $unit->id,
                        'title' => "Topic {$i}.{$j}: Syllabics Practice",
                        'description' => "Learn Plains Cree syllabics - Topic {$i}.{$j}",
                        'order' => $j,
                        'status' => 'published',
                        'metadata' => [
                            'proficiency_level' => 'A1',
                            'syllabics_instruction' => true,
                            'cultural_notes' => "Traditional knowledge for topic {$i}.{$j}",
                            'sequential_requirement' => $j > 1 ? "topic_{$i}_" . ($j - 1) : null,
                            'xp_reward' => 50,
                            'max_level' => 5
                        ],
                        'created_by' => $this->teamUser->id,
                    ]);

                    $this->testTopics["{$i}_{$j}"] = $topic;
                }
            }
        });
    }

    /**  */
    public function test_student_can_access_units_for_enrolled_learning_path()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get units for learning path
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$this->learningPath->id}/units");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'order',
                        'status',
                        'metadata' => [
                            'proficiency_level',
                            'syllabics_focus',
                            'cultural_context',
                            'sequential_requirement',
                            'estimated_duration',
                            'xp_reward'
                        ],
                        'is_unlocked',
                        'progress_percentage'
                    ]
                ]
            ]);

        $units = $response->json('data');
        $this->assertCount(3, $units);

        // First unit should be unlocked, others locked initially
        $this->assertTrue($units[0]['is_unlocked']);
        $this->assertFalse($units[1]['is_unlocked']);
        $this->assertFalse($units[2]['is_unlocked']);

        // Verify syllabics focus
        foreach ($units as $unit) {
            $this->assertTrue($unit['metadata']['syllabics_focus']);
            $this->assertEquals('A1', $unit['metadata']['proficiency_level']);
        }
    }

    /**  */
    public function test_student_can_access_individual_unit_with_syllabics_content()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $unit = $this->testUnits[1]; // First unit should be accessible

        // API call to get specific unit
        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$unit->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'order',
                    'metadata' => [
                        'syllabics_focus',
                        'cultural_context',
                        'proficiency_level'
                    ],
                    'topics' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'order',
                            'metadata'
                        ]
                    ],
                    'progress_percentage',
                    'is_unlocked'
                ]
            ]);

        $unitData = $response->json('data');
        $this->assertEquals($unit->id, $unitData['id']);
        $this->assertTrue($unitData['metadata']['syllabics_focus']);
        $this->assertEquals('A1', $unitData['metadata']['proficiency_level']);
        $this->assertTrue($unitData['is_unlocked']);
        $this->assertCount(2, $unitData['topics']); // Should have 2 topics
    }

    /**  */
    public function test_student_cannot_access_locked_unit_due_to_sequential_learning()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $lockedUnit = $this->testUnits[2]; // Second unit should be locked

        // API call to get locked unit
        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$lockedUnit->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unit is locked. Complete previous units first.'
            ]);
    }

    /**  */
    public function test_student_can_unlock_next_unit_after_completing_previous()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Complete first unit
        $this->completeUnit($this->testUnits[1]);

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get units - second unit should now be unlocked
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$this->learningPath->id}/units");

        $response->assertStatus(200);

        $units = $response->json('data');
        $this->assertTrue($units[0]['is_unlocked']); // First unit
        $this->assertTrue($units[1]['is_unlocked']); // Second unit now unlocked
        $this->assertFalse($units[2]['is_unlocked']); // Third unit still locked
    }

    /**  */
    public function test_student_can_access_topics_within_unlocked_unit()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $unit = $this->testUnits[1]; // First unit

        // API call to get topics for unit
        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$unit->id}/topics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'order',
                        'metadata' => [
                            'proficiency_level',
                            'syllabics_instruction',
                            'cultural_notes',
                            'xp_reward'
                        ],
                        'is_unlocked',
                        'progress_percentage'
                    ]
                ]
            ]);

        $topics = $response->json('data');
        $this->assertCount(2, $topics);

        // First topic should be unlocked
        $this->assertTrue($topics[0]['is_unlocked']);

        // Verify syllabics instruction
        foreach ($topics as $topic) {
            $this->assertTrue($topic['metadata']['syllabics_instruction']);
            $this->assertEquals('A1', $topic['metadata']['proficiency_level']);
            $this->assertArrayHasKey('cultural_notes', $topic['metadata']);
        }
    }

    /**  */
    public function test_student_can_access_individual_topic_with_cultural_context()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $topic = $this->testTopics['1_1']; // First topic in first unit

        // API call to get specific topic
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$topic->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'order',
                    'metadata' => [
                        'proficiency_level',
                        'syllabics_instruction',
                        'cultural_notes',
                        'xp_reward',
                        'max_level'
                    ],
                    'lessons' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'order'
                        ]
                    ],
                    'progress_percentage',
                    'is_unlocked'
                ]
            ]);

        $topicData = $response->json('data');
        $this->assertEquals($topic->id, $topicData['id']);
        $this->assertTrue($topicData['metadata']['syllabics_instruction']);
        $this->assertEquals('A1', $topicData['metadata']['proficiency_level']);
        $this->assertArrayHasKey('cultural_notes', $topicData['metadata']);
        $this->assertTrue($topicData['is_unlocked']);
    }

    /**  */
    public function test_student_can_get_topic_progress_with_syllabics_tracking()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Create some progress for the topic
        $topic = $this->testTopics['1_1'];
        $this->createTopicProgress($topic, 60);

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get topic progress
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$topic->id}/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'topic_id',
                    'progress_percentage',
                    'completed',
                    'last_accessed_at',
                    'syllabics_mastery_level',
                    'cultural_knowledge_points',
                    'lessons_completed',
                    'total_lessons',
                    'xp_earned'
                ]
            ]);

        $progressData = $response->json('data');
        $this->assertEquals($topic->id, $progressData['topic_id']);
        $this->assertEquals(60, $progressData['progress_percentage']);
        $this->assertFalse($progressData['completed']);
        $this->assertArrayHasKey('syllabics_mastery_level', $progressData);
    }

    /**  */
    public function test_student_cannot_access_topics_in_locked_unit()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $lockedUnit = $this->testUnits[2]; // Third unit should be locked

        // API call to get topics for locked unit
        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$lockedUnit->id}/topics");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unit is locked. Complete previous units first.'
            ]);
    }

    /**  */
    public function test_student_can_track_unit_progress_with_topic_breakdown()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Create progress for topics in first unit
        $this->createTopicProgress($this->testTopics['1_1'], 100); // Complete first topic
        $this->createTopicProgress($this->testTopics['1_2'], 50);  // Partial second topic

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $unit = $this->testUnits[1];

        // API call to get unit progress
        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$unit->id}/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'unit_id',
                    'progress_percentage',
                    'completed',
                    'topics_progress' => [
                        '*' => [
                            'topic_id',
                            'title',
                            'progress_percentage',
                            'completed',
                            'syllabics_mastery'
                        ]
                    ],
                    'overall_syllabics_mastery',
                    'cultural_knowledge_score',
                    'estimated_completion_time'
                ]
            ]);

        $progressData = $response->json('data');
        $this->assertEquals($unit->id, $progressData['unit_id']);
        $this->assertEquals(75, $progressData['progress_percentage']); // (100 + 50) / 2
        $this->assertFalse($progressData['completed']);
        $this->assertCount(2, $progressData['topics_progress']);
    }

    /**  */
    public function test_student_can_get_units_with_syllabics_mastery_indicators()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Create syllabics mastery progress
        $this->createSyllabicsMasteryProgress();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get units with mastery indicators
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$this->learningPath->id}/units?include_mastery=true");

        $response->assertStatus(200);

        $units = $response->json('data');
        foreach ($units as $unit) {
            $this->assertArrayHasKey('syllabics_mastery_level', $unit);
            $this->assertArrayHasKey('cultural_knowledge_score', $unit);
            $this->assertArrayHasKey('mastery_indicators', $unit);
        }
    }

    /**  */
    public function test_student_cannot_access_units_without_enrollment()
    {
        // Don't enroll student in learning path

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get units without enrollment
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$this->learningPath->id}/units");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You must be enrolled in this learning path to access its content.'
            ]);
    }

    // Helper methods
    private function enrollStudentInLearningPath(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            UserLearningPath::create([
                'user_id' => $this->studentUser->id,
                'learning_path_id' => $this->learningPath->id,
                'enrolled_at' => now(),
                'status' => 'active'
            ]);
        });
    }

    private function completeUnit(Unit $unit): void
    {
        $this->runInTenantContext($this->tenant, function () use ($unit) {
            UserProgress::create([
                'user_id' => $this->studentUser->id,
                'trackable_type' => Unit::class,
                'trackable_id' => $unit->id,
                'progress_percentage' => 100,
                'completed' => true,
                'completed_at' => now(),
                'last_accessed_at' => now(),
            ]);
        });
    }

    protected function createTopicProgress(Topic $topic, int $percentage): void
    {
        $this->runInTenantContext($this->tenant, function () use ($topic, $percentage) {
            UserProgress::create([
                'user_id' => $this->studentUser->id,
                'trackable_type' => Topic::class,
                'trackable_id' => $topic->id,
                'progress_percentage' => $percentage,
                'completed' => $percentage >= 100,
                'completed_at' => $percentage >= 100 ? now() : null,
                'last_accessed_at' => now(),
                'metadata' => [
                    'syllabics_mastery_level' => $percentage >= 80 ? 'advanced' : ($percentage >= 50 ? 'intermediate' : 'beginner'),
                    'cultural_knowledge_points' => intval($percentage / 10),
                ]
            ]);
        });
    }

    protected function createSyllabicsMasteryProgress(array $attributes = []): void
    {
        $this->runInTenantContext($this->tenant, function () {
            // Create mastery indicators for the first unit
            $unit = $this->testUnits[1];
            UserProgress::create([
                'user_id' => $this->studentUser->id,
                'trackable_type' => Unit::class,
                'trackable_id' => $unit->id,
                'progress_percentage' => 75,
                'completed' => false,
                'last_accessed_at' => now(),
                'metadata' => [
                    'syllabics_mastery_level' => 'intermediate',
                    'cultural_knowledge_score' => 85,
                    'mastery_indicators' => [
                        'syllabics_reading' => 80,
                        'syllabics_writing' => 70,
                        'cultural_context' => 85,
                        'pronunciation' => 75
                    ]
                ]
            ]);
        });
    }
}
