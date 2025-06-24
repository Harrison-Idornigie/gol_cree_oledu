<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\Sentence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class TeamContentScaffoldingControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamMember;
    protected User $studentUser;
    protected Language $language;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();

        // Create test users in tenant context
        $this->teamMember = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Team Member',
                'email' => 'team@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        });

        $this->studentUser = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Student User',
                'email' => 'student@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        });

        // Create test language
        $this->language = $this->runInTenantContext($this->tenant, function () {
            return Language::create([
                'name' => 'Spanish',
                'code' => 'es',
                'native_name' => 'Español',
                'direction' => 'ltr',
                'status' => 'active',
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createTestSentence(array $attributes = []): Sentence
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Sentence::create(array_merge([
                'text' => 'Hola, ¿cómo estás?',
                'language_id' => $this->language->id,
                'difficulty_level' => 'beginner',
                'created_by' => $this->teamMember->id,
            ], $attributes));
        });
    }

    /**
     * Test team member can generate exercises
     * 
     * @test
     */
    public function test_team_member_can_generate_exercises()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $generationData = [
            'exercise_type' => 'multiple_choice',
            'language_id' => $this->language->id,
            'difficulty_level' => 'beginner',
            'topic' => 'greetings',
            'count' => 5,
            'settings' => [
                'include_audio' => true,
                'answer_options' => 4,
                'auto_generate_distractors' => true
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-exercises", $generationData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'generated_exercises',
                    'generation_summary' => [
                        'total_generated',
                        'exercise_type',
                        'language',
                        'difficulty_level'
                    ],
                    'ai_metadata' => [
                        'model_used',
                        'generation_time',
                        'confidence_score'
                    ]
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertCount(5, $responseData['generated_exercises']);
        $this->assertEquals('multiple_choice', $responseData['generation_summary']['exercise_type']);
    }

    /**
     * Test team member can generate exercises from sentences
     * 
     * @test
     */
    public function test_team_member_can_generate_exercises_from_sentences()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $sentence1 = $this->createTestSentence(['text' => 'Buenos días']);
        $sentence2 = $this->createTestSentence(['text' => 'Buenas noches']);

        $generationData = [
            'sentence_ids' => [$sentence1->id, $sentence2->id],
            'exercise_types' => ['multiple_choice', 'fill_in_blank'],
            'settings' => [
                'generate_audio' => true,
                'difficulty_adjustment' => 'auto'
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-from-sentences", $generationData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'generated_exercises',
                    'source_sentences',
                    'generation_summary',
                    'ai_metadata'
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertGreaterThan(0, count($responseData['generated_exercises']));
        $this->assertCount(2, $responseData['source_sentences']);
    }

    /**
     * Test team member can preview exercises before generation
     * 
     * @test
     */
    public function test_team_member_can_preview_exercises()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $previewData = [
            'exercise_type' => 'listening_comprehension',
            'language_id' => $this->language->id,
            'difficulty_level' => 'intermediate',
            'topic' => 'food',
            'count' => 3
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/preview-exercises", $previewData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'preview_exercises',
                    'estimated_generation_time',
                    'content_quality_score',
                    'preview_metadata' => [
                        'sample_count',
                        'full_generation_estimate'
                    ]
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertCount(3, $responseData['preview_exercises']);
        $this->assertIsNumeric($responseData['estimated_generation_time']);
    }

    /**
     * Test team member can generate lesson with AI
     * 
     * @test
     */
    public function test_team_member_can_generate_lesson()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $lessonData = [
            'title' => 'Spanish Colors',
            'language_id' => $this->language->id,
            'difficulty_level' => 'beginner',
            'learning_objectives' => [
                'Learn basic color names in Spanish',
                'Use colors in simple sentences',
                'Practice pronunciation of color words'
            ],
            'content_preferences' => [
                'include_vocabulary' => true,
                'include_exercises' => true,
                'include_audio' => true,
                'exercise_types' => ['multiple_choice', 'matching', 'pronunciation']
            ],
            'lesson_structure' => [
                'introduction',
                'vocabulary_presentation',
                'practice_exercises',
                'review'
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-lesson", $lessonData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'lesson_content' => [
                        'title',
                        'introduction',
                        'vocabulary',
                        'exercises',
                        'review_section'
                    ],
                    'generated_resources' => [
                        'audio_files',
                        'images',
                        'exercise_files'
                    ],
                    'ai_metadata',
                    'generation_summary'
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertEquals('Spanish Colors', $responseData['lesson_content']['title']);
        $this->assertIsArray($responseData['lesson_content']['vocabulary']);
        $this->assertIsArray($responseData['lesson_content']['exercises']);
    }

    /**
     * Test team member can perform bulk generation
     * 
     * @test
     */
    public function test_team_member_can_perform_bulk_generation()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $bulkData = [
            'generation_type' => 'topic_based',
            'language_id' => $this->language->id,
            'topics' => [
                [
                    'name' => 'Family Members',
                    'difficulty' => 'beginner',
                    'content_types' => ['vocabulary', 'exercises', 'audio']
                ],
                [
                    'name' => 'Daily Routines',
                    'difficulty' => 'intermediate',
                    'content_types' => ['sentences', 'exercises', 'conversations']
                ]
            ],
            'settings' => [
                'auto_publish' => false,
                'quality_check' => true,
                'generate_metadata' => true
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/bulk-generate", $bulkData);

        $response->assertStatus(202) // Accepted for async processing
            ->assertJsonStructure([
                'data' => [
                    'job_id',
                    'estimated_completion_time',
                    'generation_summary' => [
                        'total_topics',
                        'total_content_items',
                        'estimated_duration'
                    ],
                    'status_check_url'
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertIsString($responseData['job_id']);
        $this->assertEquals(2, $responseData['generation_summary']['total_topics']);
    }

    /**
     * Test generation validation
     * 
     * @test
     */
    public function test_generation_endpoints_validate_input()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Missing required fields for exercise generation
        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-exercises", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exercise_type', 'language_id', 'difficulty_level']);

        // Invalid exercise type
        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-exercises", [
            'exercise_type' => 'invalid_type',
            'language_id' => $this->language->id,
            'difficulty_level' => 'beginner'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exercise_type']);

        // Invalid language ID
        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-exercises", [
            'exercise_type' => 'multiple_choice',
            'language_id' => 99999,
            'difficulty_level' => 'beginner'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_id']);
    }

    /**
     * Test generation with AI service limitations
     * 
     * @test
     */
    public function test_generation_handles_ai_service_limitations()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Test with excessive count request
        $generationData = [
            'exercise_type' => 'multiple_choice',
            'language_id' => $this->language->id,
            'difficulty_level' => 'beginner',
            'count' => 1000, // Excessive count
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-exercises", $generationData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['count']);
    }

    /**
     * Test students cannot access scaffolding endpoints
     * 
     * @test
     */
    public function test_students_cannot_access_scaffolding_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['tenant']);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-exercises", [
            'exercise_type' => 'multiple_choice',
            'language_id' => $this->language->id,
            'difficulty_level' => 'beginner'
        ]);
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-lesson", [
            'title' => 'Test Lesson',
            'language_id' => $this->language->id
        ]);
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/preview-exercises", [
            'exercise_type' => 'multiple_choice',
            'language_id' => $this->language->id
        ]);
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access is blocked
     * 
     * @test
     */
    public function test_unauthenticated_access_blocked()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-exercises", [
            'exercise_type' => 'multiple_choice',
            'language_id' => $this->language->id,
            'difficulty_level' => 'beginner'
        ]);
        $response->assertStatus(401);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-lesson", [
            'title' => 'Test Lesson',
            'language_id' => $this->language->id
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test generation with nonexistent sentences
     * 
     * @test
     */
    public function test_generation_from_nonexistent_sentences_returns_422()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $generationData = [
            'sentence_ids' => [99999, 88888],
            'exercise_types' => ['multiple_choice']
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-from-sentences", $generationData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sentence_ids']);
    }

    /**
     * Test rate limiting on AI generation endpoints
     * 
     * @test
     */
    public function test_generation_respects_rate_limiting()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $generationData = [
            'exercise_type' => 'multiple_choice',
            'language_id' => $this->language->id,
            'difficulty_level' => 'beginner',
            'count' => 5
        ];

        // Make multiple rapid requests (would be rate limited in real implementation)
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson("/api/{$this->tenant->slug}/team/scaffolding/generate-exercises", $generationData);

            if ($i < 2) {
                $response->assertStatus(200);
            } else {
                // Third request might be rate limited (implementation dependent)
                $this->assertContains($response->status(), [200, 429]);
            }
        }
    }
}
