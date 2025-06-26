<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class StudentLearningPathControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $language;
    protected LearningPath $learningPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);

        // Create users with different roles in tenant context
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeam();

        // Create test environment
        $this->language = $this->createLanguage();
        $this->learningPath = $this->createLearningPath();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Helper to create a test language
     */
    protected function createLanguage(array $attributes = []): Language
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Language::create(array_merge([
                'name' => 'Test Language',
                'code' => 'tl',
                'native_name' => 'Test Language Native',
                'is_active' => true
            ], $attributes));
        });
    }

    /**
     * Helper to create a test learning path
     */
    protected function createLearningPath(array $attributes = []): LearningPath
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return LearningPath::create(array_merge([
                'title' => 'Test Learning Path',
                'slug' => 'test-lp-' . Str::random(8),
                'description' => 'This is a test learning path',
                'language_id' => $this->language->id,
                'level' => 'beginner',
                'status' => 'published',
                'created_by' => $this->teamUser->id,
            ], $attributes));
        });
    }

    /**
     * Test listing all learning paths
     */
    public function test_index_success()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning paths retrieved successfully.'
            ]);
    }

    /**
     * Test showing a specific learning path
     */
    public function test_show_success()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$this->learningPath->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'description',
                    'language_id',
                    'level',
                    'status',
                    'created_by',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning path retrieved successfully.',
                'data' => [
                    'id' => $this->learningPath->id,
                    'title' => $this->learningPath->title
                ]
            ]);
    }

    /**
     * Test getting learning path progress
     */
    public function test_progress_success()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$this->learningPath->id}/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning path progress retrieved successfully.'
            ]);
    }

    /**
     * Test enrolling in a learning path
     */
    public function test_enroll_success()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$this->learningPath->id}/enroll");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Enrolled in learning path successfully.'
            ]);

        // Verify enrollment was created
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('user_learning_paths', [
                'user_id' => $this->studentUser->id,
                'learning_path_id' => $this->learningPath->id,
            ]);
        });
    }

    /**
     * Test getting learning paths by level
     */
    public function test_by_level_success()
    {
        // Create a learning path with a different level
        $this->runInTenantContext($this->tenant, function () {
            return LearningPath::create([
                'title' => 'Intermediate Learning Path',
                'slug' => 'intermediate-lp-' . Str::random(8),
                'description' => 'This is an intermediate learning path',
                'language_id' => $this->language->id,
                'level' => 'intermediate',
                'status' => 'published',
                'created_by' => $this->teamUser->id,
            ]);
        });

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/by-level/intermediate");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning paths retrieved successfully.'
            ]);

        // Verify the response contains only intermediate level paths
        $response->assertJsonFragment(['level' => 'intermediate']);
        $response->assertJsonMissing(['level' => 'beginner']);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access()
    {
        // Not authenticated
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths");
        $response->assertStatus(401);
    }

    /**
     * Test access to unpublished learning path
     */
    public function test_unpublished_learning_path_access()
    {
        // Create an unpublished learning path
        $unpublishedPath = $this->runInTenantContext($this->tenant, function () {
            return LearningPath::create([
                'title' => 'Unpublished Learning Path',
                'slug' => 'unpublished-lp-' . Str::random(8),
                'description' => 'This is an unpublished learning path',
                'language_id' => $this->language->id,
                'level' => 'beginner',
                'status' => 'draft', // Unpublished status
                'created_by' => $this->teamUser->id,
            ]);
        });

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$unpublishedPath->id}");
        $response->assertStatus(404);
    }

    /**
     * Helper method to initialize tenant context
     */
    protected function initializeTenantContext(Tenant $tenant): void
    {
        // The tenant is already initialized and seeded in createTestTenant
        // This method is kept for compatibility but not needed with enhanced trait
    }

    /**
     * Helper method to create tenant team member
     */
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

    /**
     * Helper method to create tenant student
     */
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
}
