<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\ContentTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class TeamContentTemplateControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamMember;
    protected User $studentUser;

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
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createTestContentTemplate(array $attributes = []): ContentTemplate
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return ContentTemplate::create(array_merge([
                'name' => 'Basic Exercise Template',
                'description' => 'A template for basic exercises',
                'type' => 'exercise',
                'template_data' => [
                    'exercise_type' => 'multiple_choice',
                    'question_format' => 'Choose the correct answer',
                    'answer_options' => 4,
                    'difficulty' => 'beginner'
                ],
                'tags' => ['basic', 'beginner', 'multiple-choice'],
                'is_public' => true,
                'created_by' => $this->teamMember->id,
            ], $attributes));
        });
    }

    /**
     * Test team member can list content templates
     * 
     * 
     */
    public function test_team_member_can_list_content_templates()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template1 = $this->createTestContentTemplate(['name' => 'Template 1']);
        $template2 = $this->createTestContentTemplate(['name' => 'Template 2', 'type' => 'lesson']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/content-templates");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'type',
                        'template_data',
                        'tags',
                        'is_public',
                        'created_by',
                        'usage_count',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can create content template
     * 
     * 
     */
    public function test_team_member_can_create_content_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $templateData = [
            'name' => 'New Exercise Template',
            'description' => 'A new template for exercises',
            'type' => 'exercise',
            'template_data' => [
                'exercise_type' => 'fill_in_blank',
                'difficulty' => 'intermediate'
            ],
            'tags' => ['intermediate', 'fill-blank'],
            'is_public' => false,
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/content-templates", $templateData);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Exercise Template')
            ->assertJsonPath('data.description', 'A new template for exercises')
            ->assertJsonPath('data.type', 'exercise')
            ->assertJsonPath('data.is_public', false)
            ->assertJsonPath('data.created_by', $this->teamMember->id)
            ->assertJsonPath('data.template_data.exercise_type', 'fill_in_blank');

        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('content_templates', [
                'name' => 'New Exercise Template',
                'type' => 'exercise',
                'created_by' => $this->teamMember->id,
            ]);
        });
    }

    /**
     * Test content template creation validation
     * 
     * 
     */
    public function test_content_template_creation_validation()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Missing required fields
        $response = $this->postJson("/api/{$this->tenant->slug}/team/content-templates", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'template_data']);

        // Invalid type
        $response = $this->postJson("/api/{$this->tenant->slug}/team/content-templates", [
            'name' => 'Test Template',
            'type' => 'invalid_type',
            'template_data' => []
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    /**
     * Test team member can get recommendations
     * 
     * 
     */
    public function test_team_member_can_get_template_recommendations()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/content-templates/recommendations?content_type=exercise&difficulty=beginner");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'recommended_templates',
                    'popular_templates',
                    'recently_used_templates'
                ]
            ]);
    }

    /**
     * Test team member can get templates by type
     * 
     * 
     */
    public function test_team_member_can_get_templates_by_type()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $exerciseTemplate = $this->createTestContentTemplate(['type' => 'exercise']);
        $lessonTemplate = $this->createTestContentTemplate(['type' => 'lesson']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/content-templates/by-type/exercise");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'type'
                    ]
                ]
            ]);

        // Should only return exercise templates
        $templates = $response->json('data');
        foreach ($templates as $template) {
            $this->assertEquals('exercise', $template['type']);
        }
    }

    /**
     * Test team member can show content template
     * 
     * 
     */
    public function test_team_member_can_show_content_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestContentTemplate();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/content-templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonPath('data.name', $template->name)
            ->assertJsonPath('data.type', $template->type)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'type',
                    'template_data',
                    'tags',
                    'is_public',
                    'created_by',
                    'usage_count',
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    /**
     * Test team member can update content template
     * 
     * 
     */
    public function test_team_member_can_update_content_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestContentTemplate();

        $updateData = [
            'name' => 'Updated Template Name',
            'description' => 'Updated description',
            'template_data' => [
                'exercise_type' => 'true_false',
                'difficulty' => 'advanced'
            ],
            'tags' => ['advanced', 'true-false'],
            'is_public' => true,
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/content-templates/{$template->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Template Name')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.is_public', true);

        $this->runInTenantContext($this->tenant, function () use ($template) {
            $this->assertDatabaseHas('content_templates', [
                'id' => $template->id,
                'name' => 'Updated Template Name',
                'description' => 'Updated description',
                'is_public' => true,
            ]);
        });
    }

    /**
     * Test team member can delete content template
     * 
     * 
     */
    public function test_team_member_can_delete_content_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestContentTemplate();

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/content-templates/{$template->id}");

        $response->assertStatus(204);

        $this->runInTenantContext($this->tenant, function () use ($template) {
            $this->assertDatabaseMissing('content_templates', [
                'id' => $template->id,
            ]);
        });
    }

    /**
     * Test team member can preview template
     * 
     * 
     */
    public function test_team_member_can_preview_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestContentTemplate();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/content-templates/{$template->id}/preview");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'preview_html',
                    'template_structure',
                    'sample_data'
                ]
            ]);
    }

    /**
     * Test team member can clone template
     * 
     * 
     */
    public function test_team_member_can_clone_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestContentTemplate();

        $cloneData = [
            'name' => 'Cloned Template'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/content-templates/{$template->id}/clone", $cloneData);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Cloned Template')
            ->assertJsonPath('data.type', $template->type)
            ->assertJsonPath('data.created_by', $this->teamMember->id);

        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('content_templates', [
                'name' => 'Cloned Template',
                'created_by' => $this->teamMember->id,
            ]);
        });
    }

    /**
     * Test team member can get template usage stats
     * 
     * 
     */
    public function test_team_member_can_get_template_usage_stats()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestContentTemplate();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/content-templates/{$template->id}/usage-stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_usage',
                    'usage_by_month',
                    'popular_variations',
                    'user_ratings'
                ]
            ]);
    }

    /**
     * Test team member can instantiate template
     * 
     * 
     */
    public function test_team_member_can_instantiate_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestContentTemplate();

        $instantiateData = [
            'content_data' => [
                'question' => 'What is the Spanish word for hello?',
                'answers' => ['Hola', 'Adiós', 'Gracias', 'Buenos días'],
                'correct_answer' => 0
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/content-templates/{$template->id}/instantiate", $instantiateData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'created_content_id',
                    'content_type',
                    'content_data'
                ]
            ]);
    }

    /**
     * Test students cannot access team content template endpoints
     * 
     * 
     */
    public function test_students_cannot_access_team_content_template_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['tenant']);

        $template = $this->createTestContentTemplate();

        // Test various endpoints
        $response = $this->getJson("/api/{$this->tenant->slug}/team/content-templates");
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/content-templates", [
            'name' => 'Test Template',
            'type' => 'exercise',
            'template_data' => []
        ]);
        $response->assertStatus(403);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/content-templates/{$template->id}", [
            'name' => 'Updated Template',
        ]);
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/content-templates/{$template->id}");
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access is blocked
     * 
     * 
     */
    public function test_unauthenticated_access_blocked()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/team/content-templates");
        $response->assertStatus(401);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/content-templates", [
            'name' => 'Test Template',
            'type' => 'exercise',
            'template_data' => []
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test nonexistent template returns 404
     * 
     * 
     */
    public function test_nonexistent_template_returns_404()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/content-templates/99999");
        $response->assertStatus(404);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/content-templates/99999", [
            'name' => 'Updated Template',
        ]);
        $response->assertStatus(404);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/content-templates/99999");
        $response->assertStatus(404);
    }
}
