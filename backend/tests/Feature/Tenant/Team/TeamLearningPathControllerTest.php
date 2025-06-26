<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class TeamLearningPathControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamUser;
    protected User $adminUser;
    protected User $studentUser;
    protected Language $language;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);

        // Create users with different roles in tenant context
        $this->teamUser = $this->createTenantTeam();
        $this->adminUser = $this->createTenantAdmin();
        $this->studentUser = $this->createTenantStudent();

        // Create test language
        $this->language = $this->createLanguage();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Helper to create a test language
     */
    protected function createLanguage() {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Language::create([
                'name' => 'Test Language',
                'code' => 'tl',
                'native_name' => 'Test Language Native',
                'is_active' => true
            ]);
        });
    }

    /**
     * Helper to create a test learning path
     */
    protected function createLearningPath(array $attributes = [])
    {
        $defaultAttrs = [
            'title' => 'Test Learning Path ' . rand(1000, 9999),
            'slug' => 'test-lp-' . Str::random(8),
            'description' => 'This is a test learning path',
            'language_id' => $this->language->id,
            'level' => 'beginner',
            'status' => 'draft',
            'created_by' => $this->teamUser->id,
        ];

        return $this->runInTenantContext($this->tenant, function () use ($defaultAttrs, $attributes) {
            return LearningPath::create(array_merge($defaultAttrs, $attributes));
        });
    }

    /**
     * Test listing all learning paths (index method)
     */
    public function test_index_success()
    {
        // Create some test learning paths
        $paths = [];
        for ($i = 0; $i < 3; $i++) {
            $paths[] = $this->createLearningPath();
        }

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/learning-paths");

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
     * Test creating a new learning path (store method)
     */
    public function test_store_success()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $pathData = [
            'title' => 'New Learning Path',
            'slug' => 'new-learning-path',
            'description' => 'This is a new learning path',
            'language_id' => $this->language->id,
            'level' => 'beginner',
            'status' => 'draft'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/learning-paths", $pathData);

        $response->assertStatus(201)
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
                'message' => 'Learning path created successfully.',
                'data' => [
                    'title' => 'New Learning Path',
                    'slug' => 'new-learning-path',
                    'language_id' => $this->language->id,
                ]
            ]);
    }

    /**
     * Test validation failure when creating a learning path
     */
    public function test_store_validation_failure()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        // Missing required fields
        $pathData = [
            'title' => '' // Empty title, which should fail validation
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/learning-paths", $pathData);

        $response->assertStatus(400)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test retrieving a specific learning path (show method)
     */
    public function test_show_success()
    {
        // Create test learning path
        $path = $this->createLearningPath();

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/learning-paths/{$path->id}");

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
                    'id' => $path->id,
                    'title' => $path->title
                ]
            ]);
    }

    /**
     * Test updating a learning path (update method)
     */
    public function test_update_success()
    {
        // Create test learning path
        $path = $this->createLearningPath();

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $updateData = [
            'title' => 'Updated Learning Path',
            'description' => 'This is an updated description',
            'level' => 'intermediate'
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/learning-paths/{$path->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning path updated successfully.',
                'data' => [
                    'id' => $path->id,
                    'title' => 'Updated Learning Path',
                    'description' => 'This is an updated description',
                    'level' => 'intermediate'
                ]
            ]);
    }

    /**
     * Test deleting a learning path (destroy method)
     */
    public function test_destroy_success()
    {
        // Create test learning path
        $path = $this->createLearningPath();

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/learning-paths/{$path->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning path deleted successfully.'
            ]);

        // Verify the learning path was actually deleted
        $this->runInTenantContext($this->tenant, function () use ($path) {
            $this->assertDatabaseMissing('learning_paths', ['id' => $path->id]);
        });
    }

    /**
     * Test submitting a learning path for review
     */
    public function test_submit_for_review_success()
    {
        // Create test learning path
        $path = $this->createLearningPath(['status' => 'draft']);

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/learning-paths/{$path->id}/submit-for-review");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning path submitted for review successfully.'
            ]);

        // Verify the status was updated
        $this->runInTenantContext($this->tenant, function () use ($path) {
            $this->assertDatabaseHas('learning_paths', [
                'id' => $path->id,
                'status' => 'pending_review'
            ]);
        });
    }

    /**
     * Test updating a learning path status
     */
    public function test_update_status_success()
    {
        // Create test learning path
        $path = $this->createLearningPath(['status' => 'pending_review']);

        // Authenticate as team member with admin rights
        Sanctum::actingAs($this->adminUser, ['*']);

        $statusData = [
            'status' => 'published'
        ];

        $response = $this->patchJson("/api/{$this->tenant->slug}/team/learning-paths/{$path->id}/status", $statusData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning path status updated successfully.'
            ]);

        // Verify the status was updated
        $this->runInTenantContext($this->tenant, function () use ($path) {
            $this->assertDatabaseHas('learning_paths', [
                'id' => $path->id,
                'status' => 'published'
            ]);
        });
    }

    /**
     * Test reordering units within a learning path
     */
    public function test_reorder_units_success()
    {
        // Create test learning path
        $path = $this->createLearningPath();

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        // Mock unit IDs
        $unitIds = [
            'unit_1',
            'unit_2',
            'unit_3'
        ];

        $orderData = [
            'unit_order' => $unitIds
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/learning-paths/{$path->id}/reorder-units", $orderData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Units reordered successfully.'
            ]);
    }

    /**
     * Test student user cannot access team endpoints
     */
    public function test_student_cannot_access()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/learning-paths");

        $response->assertStatus(403);
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
     * Helper method to create tenant admin
     */
    protected function createTenantAdmin(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge(['email' => 'admin@test.com',
                'membership' => 'admin',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    /**
     * Helper method to create tenant team member
     */
    protected function createTenantTeam(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge(['email' => 'team@test.com',
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
            return User::factory()->create(array_merge(['email' => 'student@test.com',
                'membership' => 'student',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }
}
