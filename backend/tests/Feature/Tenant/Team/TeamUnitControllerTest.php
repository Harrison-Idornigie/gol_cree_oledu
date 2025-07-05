<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TenantTestCase;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Unit;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class TeamUnitControllerTest extends TenantTestCase
{

    protected Tenant $tenant;
    protected User $teamMember;
    protected User $studentUser;
    protected Language $language;
    protected LearningPath $learningPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenant
        $this->tenant = $this->createTestTenant();

        // Create test users in tenant context
        $this->teamMember = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Team Member',
                'email' => 'team@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'membership' => 'team',
            ]);
        });

        $this->studentUser = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Student User',
                'email' => 'student@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'membership' => 'student',
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
                'target_level' => 'beginner',
                'status' => 'draft',
                'created_by' => $this->teamMember->id,
            ]);
        });
    }



    protected function createTestUnit(array $attributes = []): Unit
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            $unit = Unit::create(array_merge([
                'title' => 'Unit 1: Basics',
                'description' => 'Basic Spanish vocabulary and phrases',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'draft',
            ], $attributes));



            return $unit;
        });
    }



    /**
     * Test team member can list units
     *
     * 
     */
    public function test_team_member_can_list_units()
    {
        Sanctum::actingAs($this->teamMember, [], 'tenant');

        // Clear any existing units to ensure clean test
        $this->runInTenantContext($this->tenant, function () {
            Unit::query()->delete();
        });

        $unit1 = $this->createTestUnit(['title' => 'Unit 1']);
        $unit2 = $this->createTestUnit(['title' => 'Unit 2', 'order' => 2]);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/units");

        $response->assertStatus(200);

        // The response has a nested structure: data.data contains the actual units
        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Units retrieved successfully.', $responseData['message']);

        // Get the actual units from the paginated response
        $units = $responseData['data']['data'];
        $titles = collect($units)->pluck('title')->toArray();

        $this->assertContains('Unit 1', $titles);
        $this->assertContains('Unit 2', $titles);
        $this->assertGreaterThanOrEqual(2, count($units));

        // Verify the response structure
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'learning_path_id',
                        'order',
                        'status',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'total'
            ]
        ]);
    }

    /**
     * Test team member can create unit
     * 
     * 
     */
    public function test_team_member_can_create_unit()
    {
        Sanctum::actingAs($this->teamMember, [], 'tenant');

        $unitData = [
            'title' => 'New Unit',
            'description' => 'A new unit for testing',
            'learning_path_id' => $this->learningPath->id,
            'order' => 1,
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/units", $unitData);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'New Unit')
            ->assertJsonPath('data.description', 'A new unit for testing')
            ->assertJsonPath('data.learning_path_id', $this->learningPath->id)
            ->assertJsonPath('data.order', 1)
            ->assertJsonPath('data.status', 'draft');

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
     * 
     */
    public function test_unit_creation_validation()
    {
        Sanctum::actingAs($this->teamMember, [], 'tenant');

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
     * 
     */
    public function test_team_member_can_show_unit()
    {
        Sanctum::actingAs($this->teamMember, [], 'tenant');

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
                    'order',
                    'status',
                    'tenant_id',
                    'template_id',
                    'review_status',
                    'created_at',
                    'updated_at',
                    'learning_path',
                    'topics'
                ]
            ]);
    }

    /**
     * Test team member can update unit
     * 
     * 
     */
    public function test_team_member_can_update_unit()
    {
        Sanctum::actingAs($this->teamMember, [], 'tenant');

        $unit = $this->createTestUnit();

        $updateData = [
            'title' => 'Updated Unit Title',
            'description' => 'Updated description',
            'order' => 5,
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/units/{$unit->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Unit Title')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.order', 5);

        $this->runInTenantContext($this->tenant, function () use ($unit) {
            $this->assertDatabaseHas('units', [
                'id' => $unit->id,
                'title' => 'Updated Unit Title',
                'description' => 'Updated description',
                'order' => 5,
            ]);
        });
    }

    /**
     * Test team member can delete unit
     * 
     * 
     */
    public function test_team_member_can_delete_unit()
    {
        Sanctum::actingAs($this->teamMember, [], 'tenant');

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
     * 
     */
    public function test_team_member_can_submit_unit_for_review()
    {
        Sanctum::actingAs($this->teamMember, [], 'tenant');

        $unit = $this->createTestUnit();

        // Create a topic for the unit since it's required for review submission
        $this->runInTenantContext($this->tenant, function () use ($unit) {
            \App\Models\Tenants\Topic::create([
                'unit_id' => $unit->id,
                'title' => 'Test Topic',
                'slug' => 'test-topic',
                'description' => 'A test topic for the unit',
                'order' => 1,
                'status' => 'draft',
            ]);
        });

        $response = $this->postJson("/api/{$this->tenant->slug}/team/units/{$unit->id}/submit-for-review", [
            'notes' => 'Ready for review'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.review_status', 'pending');

        $this->runInTenantContext($this->tenant, function () use ($unit) {
            $this->assertDatabaseHas('units', [
                'id' => $unit->id,
                'review_status' => 'pending',
            ]);
        });
    }

    /**
     * Test team member can update unit status
     * 
     * 
     */
    public function test_team_member_can_update_unit_status()
    {
        Sanctum::actingAs($this->teamMember, [], 'tenant');

        $unit = $this->createTestUnit();

        // Set review_status to approved so it can be published
        $this->runInTenantContext($this->tenant, function () use ($unit) {
            $unit->update(['review_status' => 'approved']);
        });

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
     * 
     */
    public function test_team_member_can_reorder_unit_topics()
    {
        Sanctum::actingAs($this->teamMember, [], 'tenant');

        $unit = $this->createTestUnit();

        // Create actual topics for the unit
        $topics = $this->runInTenantContext($this->tenant, function () use ($unit) {
            $topic1 = \App\Models\Tenants\Topic::create([
                'unit_id' => $unit->id,
                'title' => 'Topic 1',
                'slug' => 'topic-1',
                'description' => 'First topic',
                'order' => 1,
                'status' => 'draft',
            ]);

            $topic2 = \App\Models\Tenants\Topic::create([
                'unit_id' => $unit->id,
                'title' => 'Topic 2',
                'slug' => 'topic-2',
                'description' => 'Second topic',
                'order' => 2,
                'status' => 'draft',
            ]);

            $topic3 = \App\Models\Tenants\Topic::create([
                'unit_id' => $unit->id,
                'title' => 'Topic 3',
                'slug' => 'topic-3',
                'description' => 'Third topic',
                'order' => 3,
                'status' => 'draft',
            ]);

            return [$topic1, $topic2, $topic3];
        });

        $reorderData = [
            'topic_ids' => [$topics[2]->id, $topics[0]->id, $topics[1]->id] // Reorder: 3, 1, 2
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
     * 
     */
    public function test_students_cannot_access_team_unit_endpoints()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

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
     * 
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
     * 
     */
    public function test_nonexistent_unit_returns_404()
    {
        Sanctum::actingAs($this->teamMember, [], 'tenant');

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
