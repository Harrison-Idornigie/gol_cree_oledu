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
use App\Models\Tenants\Word;
use App\Models\Tenants\WordTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

/**
 * Multi-Tenant Isolation Tests
 * 
 * Tests that verify tenant-specific Plains Cree content is properly isolated
 * and that the starter pack system works correctly across different tenants.
 * Ensures complete data isolation and proper access controls.
 */
class StudentMultiTenantIsolationTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Tenant $tenantC;
    protected User $studentUserA;
    protected User $studentUserB;
    protected User $studentUserC;
    protected User $teamUserA;
    protected User $teamUserB;
    protected array $tenantContent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create multiple test tenants
        $this->tenantA = $this->createTestTenant('school-district-a');
        $this->tenantB = $this->createTestTenant('school-district-b');
        $this->tenantC = $this->createTestTenant('school-district-c');

        // Create users for each tenant
        $this->createTenantUsers();

        // Create isolated content for each tenant
        $this->createTenantSpecificContent();
    }

    protected function createTenantUsers(): void
    {
        // Create users for Tenant A
        $this->initializeTenantContext($this->tenantA);
        $this->studentUserA = $this->createTenantStudent('student-a@tenanta.com');
        $this->teamUserA = $this->createTenantTeam('team-a@tenanta.com');

        // Create users for Tenant B
        $this->initializeTenantContext($this->tenantB);
        $this->studentUserB = $this->createTenantStudent('student-b@tenantb.com');
        $this->teamUserB = $this->createTenantTeam('team-b@tenantb.com');

        // Create users for Tenant C
        $this->initializeTenantContext($this->tenantC);
        $this->studentUserC = $this->createTenantStudent('student-c@tenantc.com');
    }

    protected function createTenantSpecificContent(): void
    {
        // Create content for Tenant A
        $this->createContentForTenant($this->tenantA, 'A', $this->teamUserA);

        // Create content for Tenant B
        $this->createContentForTenant($this->tenantB, 'B', $this->teamUserB);

        // Create content for Tenant C (minimal content)
        $this->createContentForTenant($this->tenantC, 'C', null);
    }

    protected function createContentForTenant(Tenant $tenant, string $tenantLabel, ?User $teamUser): void
    {
        $this->runInTenantContext($tenant, function () use ($tenantLabel, $teamUser) {
            // Create language
            $language = Language::create([
                'name' => 'Plains Cree',
                'code' => 'crk',
                'native_name' => 'nēhiyawēwin',
                'is_active' => true,
                'metadata' => [
                    'writing_system' => 'syllabics',
                    'has_audio' => true,
                    'cultural_context' => 'indigenous',
                    'starter_pack' => true,
                    'tenant_customization' => "Tenant {$tenantLabel} specific content"
                ]
            ]);

            // Create vocabulary specific to this tenant
            $words = $this->createTenantVocabulary($language, $tenantLabel, $teamUser);

            // Create learning path
            $learningPath = LearningPath::create([
                'title' => "Plains Cree A1 - Tenant {$tenantLabel}",
                'slug' => "plains-cree-a1-tenant-{$tenantLabel}-" . Str::random(8),
                'description' => "Plains Cree A1 course customized for Tenant {$tenantLabel}",
                'language_id' => $language->id,
                'target_level' => 'A1',
                'status' => 'published',
                'metadata' => [
                    'tenant_specific' => true,
                    'tenant_label' => $tenantLabel,
                    'is_starter_pack' => true,
                    'customization_level' => $tenantLabel === 'C' ? 'minimal' : 'full',
                    'content_source' => $tenantLabel === 'C' ? 'default_template' : 'custom_created'
                ],
                'created_by' => $teamUser?->id ?? 1,
            ]);

            // Create unit
            $unit = Unit::create([
                'learning_path_id' => $learningPath->id,
                'title' => "Unit 1: Greetings - Tenant {$tenantLabel}",
                'description' => "Learn greetings specific to Tenant {$tenantLabel} community",
                'order' => 1,
                'status' => 'published',
                'metadata' => [
                    'tenant_specific' => true,
                    'tenant_label' => $tenantLabel,
                    'community_context' => "Tenant {$tenantLabel} community traditions"
                ],
                'created_by' => $teamUser?->id ?? 1,
            ]);

            // Create topic
            $topic = Topic::create([
                'unit_id' => $unit->id,
                'title' => "Basic Greetings - Tenant {$tenantLabel}",
                'description' => "Learn how to greet people in Tenant {$tenantLabel} style",
                'order' => 1,
                'status' => 'published',
                'metadata' => [
                    'tenant_specific' => true,
                    'tenant_label' => $tenantLabel
                ],
                'created_by' => $teamUser?->id ?? 1,
            ]);

            // Create lesson
            $lesson = Lesson::create([
                'topic_id' => $topic->id,
                'title' => "Saying Hello - Tenant {$tenantLabel}",
                'description' => "Learn the greeting 'tanisi' in Tenant {$tenantLabel} context",
                'order' => 1,
                'status' => 'published',
                'metadata' => [
                    'tenant_specific' => true,
                    'tenant_label' => $tenantLabel
                ],
                'created_by' => $teamUser?->id ?? 1,
            ]);

            // Create exercise
            $exercise = Exercise::create([
                'lesson_id' => $lesson->id,
                'title' => "Greeting Practice - Tenant {$tenantLabel}",
                'type' => 'matching',
                'order' => 1,
                'status' => 'published',
                'content' => [
                    'instruction' => "Match greetings in Tenant {$tenantLabel} style",
                    'tenant_specific_content' => true,
                    'tenant_label' => $tenantLabel
                ],
                'metadata' => [
                    'tenant_specific' => true,
                    'tenant_label' => $tenantLabel
                ],
                'created_by' => $teamUser?->id ?? 1,
            ]);

            $this->tenantContent[$tenantLabel] = [
                'language' => $language,
                'words' => $words,
                'learning_path' => $learningPath,
                'unit' => $unit,
                'topic' => $topic,
                'lesson' => $lesson,
                'exercise' => $exercise
            ];
        });
    }

    protected function createTenantVocabulary(Language $language, string $tenantLabel, ?User $teamUser): array
    {
        $words = [];
        $vocabularyData = [
            ['text' => 'tanisi', 'syllabics' => 'ᑕᓂᓯ', 'translation' => 'hello'],
            ['text' => 'atim', 'syllabics' => 'ᐊᑎᒼ', 'translation' => 'dog'],
            ['text' => 'nīpiy', 'syllabics' => 'ᓃᐱᕀ', 'translation' => 'water'],
        ];

        foreach ($vocabularyData as $wordData) {
            $word = Word::create([
                'text' => $wordData['text'],
                'language_id' => $language->id,
                'part_of_speech' => 'noun',
                'status' => 'published',
                'metadata' => [
                    'syllabics' => $wordData['syllabics'],
                    'proficiency_level' => 'A1',
                    'audio_url' => "audio/crk/tenant-{$tenantLabel}/{$wordData['text']}.mp3",
                    'cultural_notes' => "Traditional Plains Cree word - Tenant {$tenantLabel} context",
                    'tenant_specific' => true,
                    'tenant_label' => $tenantLabel
                ],
                'created_by' => $teamUser?->id ?? 1
            ]);

            // Create English language for translations
            $englishLanguage = Language::firstOrCreate([
                'code' => 'en'
            ], [
                'name' => 'English',
                'native_name' => 'English',
                'is_active' => true
            ]);

            WordTranslation::create([
                'word_id' => $word->id,
                'language_id' => $englishLanguage->id,
                'translation' => $wordData['translation'],
                'is_primary' => true,
                'created_by' => $teamUser?->id ?? 1
            ]);

            $words[] = $word;
        }

        return $words;
    }

    /** @test */
    public function student_can_only_access_content_from_their_own_tenant()
    {
        // Authenticate as student from Tenant A
        Sanctum::actingAs($this->studentUserA, [], 'tenant');

        // API call to get learning paths from Tenant A
        $response = $this->getJson("/api/{$this->tenantA->slug}/student/learning-paths");

        $response->assertStatus(200);

        $learningPaths = $response->json('data.data');
        $this->assertCount(1, $learningPaths);

        // Verify the learning path belongs to Tenant A
        $learningPath = $learningPaths[0];
        $this->assertEquals('A', $learningPath['metadata']['tenant_label']);
        $this->assertStringContainsString('Tenant A', $learningPath['title']);
    }

    /** @test */
    public function student_cannot_access_content_from_other_tenants()
    {
        // Authenticate as student from Tenant A
        Sanctum::actingAs($this->studentUserA, [], 'tenant');

        $tenantBLearningPath = $this->tenantContent['B']['learning_path'];

        // Try to access learning path from Tenant B using Tenant A's API
        $response = $this->getJson("/api/{$this->tenantA->slug}/student/learning-paths/{$tenantBLearningPath->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function student_cannot_access_other_tenant_api_endpoints()
    {
        // Authenticate as student from Tenant A
        Sanctum::actingAs($this->studentUserA, [], 'tenant');

        // Try to access Tenant B's API endpoints
        $response = $this->getJson("/api/{$this->tenantB->slug}/student/learning-paths");

        // Should fail because student A is not authorized for Tenant B
        $response->assertStatus(401);
    }

    /** @test */
    public function tenant_specific_vocabulary_is_properly_isolated()
    {
        // Authenticate as student from Tenant A
        Sanctum::actingAs($this->studentUserA, [], 'tenant');

        // API call to get words from Tenant A
        $response = $this->getJson("/api/{$this->tenantA->slug}/student/words");

        $response->assertStatus(200);

        $words = $response->json('data.data');
        foreach ($words as $word) {
            $this->assertEquals('A', $word['metadata']['tenant_label']);
            $this->assertStringContainsString('Tenant A', $word['metadata']['cultural_notes']);
            $this->assertStringContainsString('tenant-A', $word['metadata']['audio_url']);
        }
    }

    /** @test */
    public function starter_pack_system_works_correctly_across_tenants()
    {
        // Each tenant should have their own starter pack content

        // Check Tenant A
        Sanctum::actingAs($this->studentUserA, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenantA->slug}/student/languages?starter_pack=true");
        $response->assertStatus(200);

        $languages = $response->json('data.data');
        $plainsCree = collect($languages)->firstWhere('code', 'crk');
        $this->assertNotNull($plainsCree);
        $this->assertTrue($plainsCree['metadata']['starter_pack']);
        $this->assertEquals('Tenant A specific content', $plainsCree['metadata']['tenant_customization']);

        // Check Tenant B
        Sanctum::actingAs($this->studentUserB, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenantB->slug}/student/languages?starter_pack=true");
        $response->assertStatus(200);

        $languages = $response->json('data.data');
        $plainsCree = collect($languages)->firstWhere('code', 'crk');
        $this->assertNotNull($plainsCree);
        $this->assertTrue($plainsCree['metadata']['starter_pack']);
        $this->assertEquals('Tenant B specific content', $plainsCree['metadata']['tenant_customization']);
    }

    /** @test */
    public function tenant_enrollment_data_is_isolated()
    {
        // Enroll student A in Tenant A's learning path
        $this->enrollStudentInTenantLearningPath($this->tenantA, $this->studentUserA, 'A');

        // Enroll student B in Tenant B's learning path
        $this->enrollStudentInTenantLearningPath($this->tenantB, $this->studentUserB, 'B');

        // Check that student A can only see their own enrollment
        Sanctum::actingAs($this->studentUserA, [], 'tenant');
        $response = $this->getJson("/api/{$this->tenantA->slug}/student/learning-paths?include_enrollment_status=true");

        $response->assertStatus(200);
        $learningPaths = $response->json('data.data');
        $enrolledPath = collect($learningPaths)->firstWhere('is_enrolled', true);

        $this->assertNotNull($enrolledPath);
        $this->assertEquals('A', $enrolledPath['metadata']['tenant_label']);

        // Check that student B can only see their own enrollment
        Sanctum::actingAs($this->studentUserB, [], 'tenant');
        $response = $this->getJson("/api/{$this->tenantB->slug}/student/learning-paths?include_enrollment_status=true");

        $response->assertStatus(200);
        $learningPaths = $response->json('data.data');
        $enrolledPath = collect($learningPaths)->firstWhere('is_enrolled', true);

        $this->assertNotNull($enrolledPath);
        $this->assertEquals('B', $enrolledPath['metadata']['tenant_label']);
    }

    /** @test */
    public function tenant_progress_data_is_isolated()
    {
        // Enroll students and create progress
        $this->enrollStudentInTenantLearningPath($this->tenantA, $this->studentUserA, 'A');
        $this->createProgressForStudent($this->tenantA, $this->studentUserA, 'A');

        $this->enrollStudentInTenantLearningPath($this->tenantB, $this->studentUserB, 'B');
        $this->createProgressForStudent($this->tenantB, $this->studentUserB, 'B');

        // Check student A's progress
        Sanctum::actingAs($this->studentUserA, [], 'tenant');
        $learningPath = $this->tenantContent['A']['learning_path'];
        $response = $this->getJson("/api/{$this->tenantA->slug}/student/learning-paths/{$learningPath->id}/progress");

        $response->assertStatus(200);
        $progressData = $response->json('data');
        $this->assertEquals($learningPath->id, $progressData['learning_path_id']);

        // Check student B's progress
        Sanctum::actingAs($this->studentUserB, [], 'tenant');
        $learningPath = $this->tenantContent['B']['learning_path'];
        $response = $this->getJson("/api/{$this->tenantB->slug}/student/learning-paths/{$learningPath->id}/progress");

        $response->assertStatus(200);
        $progressData = $response->json('data');
        $this->assertEquals($learningPath->id, $progressData['learning_path_id']);
    }

    /** @test */
    public function tenant_exercise_attempts_are_isolated()
    {
        // Enroll students
        $this->enrollStudentInTenantLearningPath($this->tenantA, $this->studentUserA, 'A');
        $this->enrollStudentInTenantLearningPath($this->tenantB, $this->studentUserB, 'B');

        // Student A submits exercise answer
        Sanctum::actingAs($this->studentUserA, [], 'tenant');
        $exerciseA = $this->tenantContent['A']['exercise'];
        $response = $this->postJson("/api/{$this->tenantA->slug}/student/exercises/{$exerciseA->id}/submit-answer", [
            'answer' => 'hello',
            'question_index' => 0
        ]);
        $response->assertStatus(200);

        // Student B submits exercise answer
        Sanctum::actingAs($this->studentUserB, [], 'tenant');
        $exerciseB = $this->tenantContent['B']['exercise'];
        $response = $this->postJson("/api/{$this->tenantB->slug}/student/exercises/{$exerciseB->id}/submit-answer", [
            'answer' => 'hello',
            'question_index' => 0
        ]);
        $response->assertStatus(200);

        // Verify attempts are isolated - Student A cannot see Student B's attempts
        Sanctum::actingAs($this->studentUserA, [], 'tenant');
        $response = $this->getJson("/api/{$this->tenantA->slug}/student/exercises/{$exerciseA->id}/attempts");
        $response->assertStatus(200);

        $attempts = $response->json('data');
        $this->assertCount(1, $attempts); // Only student A's attempt
    }

    /** @test */
    public function cross_tenant_data_leakage_prevention()
    {
        // Try various cross-tenant access attempts that should all fail

        Sanctum::actingAs($this->studentUserA, [], 'tenant');

        // Try to access Tenant B's content through Tenant A's API
        $tenantBContent = $this->tenantContent['B'];

        $endpoints = [
            "/api/{$this->tenantA->slug}/student/units/{$tenantBContent['unit']->id}",
            "/api/{$this->tenantA->slug}/student/topics/{$tenantBContent['topic']->id}",
            "/api/{$this->tenantA->slug}/student/lessons/{$tenantBContent['lesson']->id}",
            "/api/{$this->tenantA->slug}/student/exercises/{$tenantBContent['exercise']->id}",
            "/api/{$this->tenantA->slug}/student/words/{$tenantBContent['words'][0]->id}",
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $this->assertEquals(404, $response->getStatusCode(), "Endpoint {$endpoint} should return 404");
        }
    }

    /** @test */
    public function tenant_customization_levels_are_respected()
    {
        // Tenant C has minimal customization (template-based)
        Sanctum::actingAs($this->studentUserC, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenantC->slug}/student/learning-paths");
        $response->assertStatus(200);

        $learningPaths = $response->json('data.data');
        $this->assertCount(1, $learningPaths);

        $learningPath = $learningPaths[0];
        $this->assertEquals('minimal', $learningPath['metadata']['customization_level']);
        $this->assertEquals('default_template', $learningPath['metadata']['content_source']);
    }

    // Helper methods
    private function enrollStudentInTenantLearningPath(Tenant $tenant, User $student, string $tenantLabel): void
    {
        $this->runInTenantContext($tenant, function () use ($student, $tenantLabel) {
            \Illuminate\Support\Facades\DB::table('user_learning_paths')->insert([
                'id' => Str::uuid(),
                'user_id' => $student->id,
                'learning_path_id' => $this->tenantContent[$tenantLabel]['learning_path']->id,
                'enrolled_at' => now(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function createProgressForStudent(Tenant $tenant, User $student, string $tenantLabel): void
    {
        $this->runInTenantContext($tenant, function () use ($student, $tenantLabel) {
            \Illuminate\Support\Facades\DB::table('user_progress')->insert([
                'id' => Str::uuid(),
                'user_id' => $student->id,
                'trackable_type' => LearningPath::class,
                'trackable_id' => $this->tenantContent[$tenantLabel]['learning_path']->id,
                'progress_percentage' => 25,
                'completed' => false,
                'last_accessed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function createTenantStudent(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'membership_type' => 'student',
            'email_verified_at' => now(),
        ]);
    }

    private function createTenantTeam(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'membership_type' => 'team',
            'email_verified_at' => now(),
        ]);
    }
}
