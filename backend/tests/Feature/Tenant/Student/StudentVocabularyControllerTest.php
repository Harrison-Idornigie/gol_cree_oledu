<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class StudentVocabularyControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected array $testData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();

        // Create users with different roles in tenant context
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeamMember();

        // Setup test data
        $this->testData = $this->setupTestData();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Helper to create a tenant team member
     */
    protected function createTenantTeamMember()
    {
        return $this->runInTenantContext($this->tenant, function () {
            $user = User::create([
                'name' => 'Team User',
                'email' => 'team_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);

            // Assign team role
            try {
                // Direct DB insert to user_permissions for compatibility
                if (\Illuminate\Support\Facades\Schema::hasTable('user_permissions')) {
                    \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'permission' => 'team',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // If we're using membership_type
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'membership_type')) {
                    $user->membership_type = 'team';
                    $user->save();
                }
            } catch (\Exception $e) {
                // Role assignment might fail if tables don't exist yet
            }

            return $user;
        });
    }

    /**
     * Helper to create a tenant student
     */
    protected function createTenantStudent()
    {
        return $this->runInTenantContext($this->tenant, function () {
            $user = User::create([
                'name' => 'Student User',
                'email' => 'student_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);

            // Assign student role
            try {
                // Direct DB insert to user_permissions for compatibility
                if (\Illuminate\Support\Facades\Schema::hasTable('user_permissions')) {
                    \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'permission' => 'student',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // If we're using membership_type
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'membership_type')) {
                    $user->membership_type = 'student';
                    $user->save();
                }
            } catch (\Exception $e) {
                // Role assignment might fail if tables don't exist yet
            }

            return $user;
        });
    }

    /**
     * Setup test data for vocabulary items, etc.
     */
    protected function setupTestData()
    {
        return $this->runInTenantContext($this->tenant, function () {
            // Create language for testing
            $languageId = \Illuminate\Support\Facades\DB::table('languages')->insertGetId([
                'name' => 'Test Language',
                'code' => 'tl',
                'native_name' => 'Test Native',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create another language for translations
            $nativeLanguageId = \Illuminate\Support\Facades\DB::table('languages')->insertGetId([
                'name' => 'Native Language',
                'code' => 'nl',
                'native_name' => 'Native Language',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create learning path
            $learningPathId = (string) Str::uuid();
            \Illuminate\Support\Facades\DB::table('learning_paths')->insert([
                'id' => $learningPathId,
                'title' => 'Test Learning Path',
                'description' => 'A test learning path',
                'language_id' => $languageId,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create unit
            $unitId = (string) Str::uuid();
            \Illuminate\Support\Facades\DB::table('units')->insert([
                'id' => $unitId,
                'learning_path_id' => $learningPathId,
                'title' => 'Test Unit',
                'description' => 'Test unit description',
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create vocabulary items
            $vocabItems = [];

            for ($i = 1; $i <= 5; $i++) {
                $vocabId = (string) Str::uuid();

                \Illuminate\Support\Facades\DB::table('vocabularies')->insert([
                    'id' => $vocabId,
                    'word' => "Word {$i}",
                    'language_id' => $languageId,
                    'translation' => "Translation {$i}",
                    'translation_language_id' => $nativeLanguageId,
                    'example_sentence' => "This is an example sentence with Word {$i}.",
                    'created_by' => $this->teamUser->id,
                    'status' => 'published',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Assign vocabulary to unit
                \Illuminate\Support\Facades\DB::table('unit_vocabularies')->insert([
                    'id' => (string) Str::uuid(),
                    'unit_id' => $unitId,
                    'vocabulary_id' => $vocabId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $vocabItems[] = $vocabId;
            }

            // Create user vocabulary progress for first item
            \Illuminate\Support\Facades\DB::table('user_vocabularies')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->studentUser->id,
                'vocabulary_id' => $vocabItems[0],
                'status' => 'learned',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create a mistake record for second item
            \Illuminate\Support\Facades\DB::table('user_vocabulary_mistakes')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->studentUser->id,
                'vocabulary_id' => $vocabItems[1],
                'mistake_count' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return [
                'language_id' => $languageId,
                'native_language_id' => $nativeLanguageId,
                'learning_path_id' => $learningPathId,
                'unit_id' => $unitId,
                'vocabulary_items' => $vocabItems
            ];
        });
    }

    /** @test */
    public function student_can_view_all_vocabulary_items()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get all vocabulary items
        $response = $this->getJson("/api/{$this->tenant->slug}/student/vocabulary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'word',
                        'translation',
                        'example_sentence',
                        'status',
                        'user_status'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'total'
                ]
            ])
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function student_can_view_vocabulary_for_specific_unit()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get unit vocabulary
        $response = $this->getJson("/api/{$this->tenant->slug}/student/vocabulary/unit/{$this->testData['unit_id']}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'word',
                        'translation',
                        'example_sentence',
                        'status',
                        'user_status' // Should include student's learning status
                    ]
                ]
            ])
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function student_can_view_individual_vocabulary_item()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Get first vocabulary item
        $vocabId = $this->testData['vocabulary_items'][0];

        // API call to get specific vocabulary item
        $response = $this->getJson("/api/{$this->tenant->slug}/student/vocabulary/{$vocabId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'word',
                    'language_id',
                    'translation',
                    'translation_language_id',
                    'example_sentence',
                    'status',
                    'user_status',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'id' => $vocabId,
                'user_status' => 'learned' // This vocabulary was marked as learned for the student
            ]);
    }

    /** @test */
    public function student_can_view_review_vocabulary()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get review items
        $response = $this->getJson("/api/{$this->tenant->slug}/student/vocabulary/review");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'word',
                        'translation',
                        'example_sentence',
                        'user_status',
                        'last_reviewed_at'
                    ]
                ]
            ]);
    }

    /** @test */
    public function student_can_view_mistake_vocabulary()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get mistake items
        $response = $this->getJson("/api/{$this->tenant->slug}/student/vocabulary/mistakes");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'word',
                        'translation',
                        'example_sentence',
                        'mistake_count'
                    ]
                ]
            ])
            ->assertJsonFragment([
                'id' => $this->testData['vocabulary_items'][1], // This should be the item with mistakes
                'mistake_count' => 2
            ]);
    }

    /** @test */
    public function student_can_check_translation()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Get first vocabulary item
        $vocabId = $this->testData['vocabulary_items'][0];

        // API call to check translation - correct answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/vocabulary/{$vocabId}/check", [
            'translation' => 'Translation 1'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'correct',
                    'expected_translation',
                    'vocabulary_id'
                ]
            ])
            ->assertJsonFragment([
                'correct' => true,
                'vocabulary_id' => $vocabId
            ]);

        // API call to check translation - incorrect answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/vocabulary/{$vocabId}/check", [
            'translation' => 'Wrong translation'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'correct',
                    'expected_translation',
                    'vocabulary_id'
                ]
            ])
            ->assertJsonFragment([
                'correct' => false,
                'expected_translation' => 'Translation 1',
                'vocabulary_id' => $vocabId
            ]);

        // Verify the mistake was recorded in DB
        $this->runInTenantContext($this->tenant, function () use ($vocabId) {
            $mistake = \Illuminate\Support\Facades\DB::table('user_vocabulary_mistakes')
                ->where('user_id', $this->studentUser->id)
                ->where('vocabulary_id', $vocabId)
                ->first();

            $this->assertNotNull($mistake);
        });
    }

    /** @test */
    public function student_can_view_vocabulary_statistics()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get vocabulary statistics
        $response = $this->getJson("/api/{$this->tenant->slug}/student/vocabulary/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_vocabulary',
                    'learned_count',
                    'in_progress_count',
                    'not_started_count',
                    'mistake_count',
                    'recent_activity'
                ]
            ])
            ->assertJsonPath('data.learned_count', 1) // We have 1 learned vocabulary
            ->assertJsonPath('data.mistake_count', 1); // We have 1 vocabulary with mistakes
    }

    /** @test */
    public function unauthenticated_user_cannot_access_vocabulary()
    {
        // API call without authentication
        $response = $this->getJson("/api/{$this->tenant->slug}/student/vocabulary");

        $response->assertStatus(401);
    }

    /** @test */
    public function student_cannot_access_unpublished_vocabulary()
    {
        // Create an unpublished vocabulary item
        $unpublishedVocabId = $this->runInTenantContext($this->tenant, function () {
            $vocabId = (string) Str::uuid();

            \Illuminate\Support\Facades\DB::table('vocabularies')->insert([
                'id' => $vocabId,
                'word' => "Unpublished Word",
                'language_id' => $this->testData['language_id'],
                'translation' => "Unpublished Translation",
                'translation_language_id' => $this->testData['native_language_id'],
                'example_sentence' => "This is an unpublished vocabulary item.",
                'created_by' => $this->teamUser->id,
                'status' => 'draft', // Unpublished
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return $vocabId;
        });

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to access unpublished vocabulary
        $response = $this->getJson("/api/{$this->tenant->slug}/student/vocabulary/{$unpublishedVocabId}");

        // Should return 404 as students shouldn't see unpublished content
        $response->assertStatus(404);
    }
}
