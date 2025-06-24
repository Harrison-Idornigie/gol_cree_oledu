<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class TeamUnitControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamMember;
    protected User $studentUser;
    protected Language $language;
    protected LearningPath $learningPath;

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

        // Create test language and learning path
        $this->language = $this->runInTenantContext($this->tenant, function () {
            return Language::create([
                'name' => 'Spanish',
                'code' => 'es',
                'native_name' => 'Español',
                'direction' => 'ltr',
                'status' => 'active',
            ]);
        });

        $this->learningPath = $this->runInTenantContext($this->tenant, function () {
            return LearningPath::create([
                'title' => 'Spanish for Beginners',
                'description' => 'Learn Spanish from scratch',
                'language_id' => $this->language->id,
                'level' => 'beginner',
                'status' => 'active',
                'created_by' => $this->teamMember->id,
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createTestUnit(array $attributes = []): Unit
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Unit::create(array_merge([
                'title' => 'Unit 1: Basics',
                'description' => 'Basic Spanish vocabulary and phrases',
                'learning_path_id' => $this->learningPath->id,
                'order_index' => 1,
                'status' => 'draft',
                'created_by' => $this->teamMember->id,
            ], $attributes));
        });
    }

    /**
     * Test team member can list units
     * 
     * @test
     */
    public function test_team_member_can_list_units()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $unit1 = $this->createTestUnit(['title' => 'Unit 1']);
        $unit2 = $this->createTestUnit(['title' => 'Unit 2', 'order_index' => 2]);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/units");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Unit 1')
            ->assertJsonPath('data.1.title', 'Unit 2')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'learning_path_id',
                        'order_index',
                        'status',
                        'created_by',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can create unit
     * 
     * @test
     */
    public function test_team_member_can_create_unit()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $unitData = [
            'title' => 'New Unit',
            'description' => 'A new unit for testing',
            'learning_path_id' => $this->learningPath->id,
            'order_index' => 1,
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/units", $unitData);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'New Unit')
            ->assertJsonPath('data.description', 'A new unit for testing')
            ->assertJsonPath('data.learning_path_id', $this->learningPath->id)
            ->assertJsonPath('data.order_index', 1)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.created_by', $this->teamMember->id);

        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('units', [
                'title' => 'New Unit',
                'description' => 'A new unit for testing',
                'learning_path_id' => $this->learningPath->id,
            ]);
        });
    }

    /**
     * Test unit creation validation
     * 
     * @test
     */
    public function test_unit_creation_validation()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Missing required fields
        $response = $this->postJson("/api/{$this->tenant->slug}/team/units", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'learning_path_id']);

        // Invalid learning path
        $response = $this->postJson("/api/{$this->tenant->slug}/team/units", [
            'title' => 'Test Unit',
            'learning_path_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['learning_path_id']);
    }

    /**
     * Test team member can show unit
     * 
     * @test
     */
    public function test_team_member_can_show_unit()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $unit = $this->createTestUnit();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/units/{$unit->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $unit->id)
            ->assertJsonPath('data.title', $unit->title)
            ->assertJsonPath('data.description', $unit->description)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'learning_path_id',
                    'order_index',
                    'status',
                    'created_by',
                    'topics_count',
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    /**
     * Test team member can update unit
     * 
     * @test
     */
    public function test_team_member_can_update_unit()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $unit = $this->createTestUnit();

        $updateData = [
            'title' => 'Updated Unit Title',
            'description' => 'Updated description',
            'order_index' => 5,
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/units/{$unit->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Unit Title')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.order_index', 5);

        $this->runInTenantContext($this->tenant, function () use ($unit) {
            $this->assertDatabaseHas('units', [
                'id' => $unit->id,
                'title' => 'Updated Unit Title',
                'description' => 'Updated description',
                'order_index' => 5,
            ]);
        });
    }

    /**
     * Test team member can delete unit
     * 
     * @test
     */
    public function test_team_member_can_delete_unit()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $unit = $this->createTestUnit();

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/units/{$unit->id}");

        $response->assertStatus(204);

        $this->runInTenantContext($this->tenant, function () use ($unit) {
            $this->assertDatabaseMissing('units', [
                'id' => $unit->id,
            ]);
        });
    }

    /**
     * Test team member can submit unit for review
     * 
     * @test
     */
    public function test_team_member_can_submit_unit_for_review()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $unit = $this->createTestUnit();

        $response = $this->postJson("/api/{$this->tenant->slug}/team/units/{$unit->id}/submit-for-review", [
            'notes' => 'Ready for review'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_review');

        $this->runInTenantContext($this->tenant, function () use ($unit) {
            $this->assertDatabaseHas('units', [
                'id' => $unit->id,
                'status' => 'pending_review',
            ]);
        });
    }

    /**
     * Test team member can update unit status
     * 
     * @test
     */
    public function test_team_member_can_update_unit_status()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $unit = $this->createTestUnit();

        $response = $this->patchJson("/api/{$this->tenant->slug}/team/units/{$unit->id}/status", [
            'status' => 'published'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $this->runInTenantContext($this->tenant, function () use ($unit) {
            $this->assertDatabaseHas('units', [
                'id' => $unit->id,
                'status' => 'published',
            ]);
        });
    }

    /**
     * Test team member can reorder unit topics
     * 
     * @test
     */
    public function test_team_member_can_reorder_unit_topics()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $unit = $this->createTestUnit();

        $reorderData = [
            'topic_ids' => [3, 1, 2] // Assuming some topic IDs
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/units/{$unit->id}/reorder-topics", $reorderData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'reordered_topics'
                ]
            ]);
    }

    /**
     * Test students cannot access team unit endpoints
     * 
     * @test
     */
    public function test_students_cannot_access_team_unit_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['tenant']);

        $unit = $this->createTestUnit();

        // Test various endpoints
        $response = $this->getJson("/api/{$this->tenant->slug}/team/units");
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/units", [
            'title' => 'Test Unit',
            'learning_path_id' => $this->learningPath->id,
        ]);
        $response->assertStatus(403);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/units/{$unit->id}", [
            'title' => 'Updated Title',
        ]);
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/units/{$unit->id}");
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access is blocked
     * 
     * @test
     */
    public function test_unauthenticated_access_blocked()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/team/units");
        $response->assertStatus(401);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/units", [
            'title' => 'Test Unit',
            'learning_path_id' => $this->learningPath->id,
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test nonexistent unit returns 404
     * 
     * @test
     */
    public function test_nonexistent_unit_returns_404()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/units/99999");
        $response->assertStatus(404);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/units/99999", [
            'title' => 'Updated Title',
        ]);
        $response->assertStatus(404);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/units/99999");
        $response->assertStatus(404);
    }
}
