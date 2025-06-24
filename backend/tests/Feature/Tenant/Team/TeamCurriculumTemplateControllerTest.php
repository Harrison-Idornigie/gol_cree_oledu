<?php

namespace Tests\Feature\Tenant\Team;

use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\CurriculumTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TenantTenantTestCase;

class TeamCurriculumTemplateControllerTest extends TenantTenantTestCase
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

    protected function createTestCurriculumTemplate(array $attributes = []): CurriculumTemplate
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return CurriculumTemplate::create(array_merge([
                'name' => 'Spanish Beginner Template',
                'description' => 'A comprehensive template for Spanish beginners',
                'language_id' => $this->language->id,
                'level' => 'beginner',
                'category' => 'general',
                'structure' => json_encode([
                    'units' => [
                        [
                            'title' => 'Basic Greetings',
                            'topics' => ['Hello', 'Goodbye', 'How are you?']
                        ],
                        [
                            'title' => 'Numbers',
                            'topics' => ['1-10', '11-20', 'Basic counting']
                        ]
                    ]
                ]),
                'objectives' => json_encode([
                    'Introduce basic Spanish greetings',
                    'Learn numbers 1-20',
                    'Practice pronunciation'
                ]),
                'duration_hours' => 40,
                'status' => 'published',
                'created_by' => $this->teamMember->id,
            ], $attributes));
        });
    }

    /**
     * Test team member can list curriculum templates
     * 
     * @test
     */
    public function test_team_member_can_list_curriculum_templates()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template1 = $this->createTestCurriculumTemplate(['name' => 'Beginner Template']);
        $template2 = $this->createTestCurriculumTemplate(['name' => 'Advanced Template', 'level' => 'advanced']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Beginner Template')
            ->assertJsonPath('data.1.name', 'Advanced Template')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'language_id',
                        'level',
                        'category',
                        'duration_hours',
                        'status',
                        'created_by',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can show curriculum template
     * 
     * @test
     */
    public function test_team_member_can_show_curriculum_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestCurriculumTemplate();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonPath('data.name', $template->name)
            ->assertJsonPath('data.description', $template->description)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'language_id',
                    'level',
                    'category',
                    'structure',
                    'objectives',
                    'duration_hours',
                    'status',
                    'created_by',
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    /**
     * Test team member can get curriculum template recommendations
     * 
     * @test
     */
    public function test_team_member_can_get_curriculum_template_recommendations()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template1 = $this->createTestCurriculumTemplate(['level' => 'beginner', 'category' => 'general']);
        $template2 = $this->createTestCurriculumTemplate(['level' => 'intermediate', 'category' => 'business']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates/recommendations?level=beginner&category=general");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'recommended' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'level',
                            'category',
                            'match_score'
                        ]
                    ],
                    'criteria' => [
                        'level',
                        'category'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can preview curriculum template
     * 
     * @test
     */
    public function test_team_member_can_preview_curriculum_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestCurriculumTemplate();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates/{$template->id}/preview");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'template_info' => [
                        'id',
                        'name',
                        'description',
                        'level',
                        'duration_hours'
                    ],
                    'structure_preview' => [
                        'units_count',
                        'topics_count',
                        'estimated_lessons'
                    ],
                    'sample_content' => [
                        'first_unit',
                        'sample_topics'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can get curriculum template effectiveness
     * 
     * @test
     */
    public function test_team_member_can_get_curriculum_template_effectiveness()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestCurriculumTemplate();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates/{$template->id}/effectiveness");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'overall_rating',
                    'completion_rate',
                    'student_satisfaction',
                    'learning_outcomes',
                    'usage_statistics' => [
                        'total_implementations',
                        'active_implementations',
                        'average_completion_time'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can get curriculum template usage
     * 
     * @test
     */
    public function test_team_member_can_get_curriculum_template_usage()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestCurriculumTemplate();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates/{$template->id}/usage");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'current_implementations' => [
                        '*' => [
                            'learning_path_id',
                            'learning_path_name',
                            'created_at',
                            'status',
                            'student_count'
                        ]
                    ],
                    'usage_metrics' => [
                        'total_implementations',
                        'this_month_implementations',
                        'average_student_progress'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can instantiate curriculum template
     * 
     * @test
     */
    public function test_team_member_can_instantiate_curriculum_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestCurriculumTemplate();

        $instantiateData = [
            'learning_path_name' => 'My Spanish Course',
            'learning_path_description' => 'A course based on the beginner template',
            'customizations' => [
                'skip_units' => [],
                'additional_topics' => ['Cultural notes'],
                'duration_adjustment' => 1.2
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/curriculum-templates/{$template->id}/instantiate", $instantiateData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'learning_path' => [
                        'id',
                        'title',
                        'description',
                        'language_id',
                        'level',
                        'status'
                    ],
                    'created_units' => [
                        '*' => [
                            'id',
                            'title',
                            'order_index'
                        ]
                    ],
                    'instantiation_summary' => [
                        'template_id',
                        'units_created',
                        'topics_created',
                        'estimated_duration'
                    ]
                ]
            ]);
    }

    /**
     * Test instantiation validation
     * 
     * @test
     */
    public function test_instantiation_validation()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestCurriculumTemplate();

        // Missing required fields
        $response = $this->postJson("/api/{$this->tenant->slug}/team/curriculum-templates/{$template->id}/instantiate", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['learning_path_name']);
    }

    /**
     * Test team member can customize curriculum template
     * 
     * @test
     */
    public function test_team_member_can_customize_curriculum_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestCurriculumTemplate();

        $customizeData = [
            'modifications' => [
                'remove_units' => [1],
                'add_topics' => [
                    'unit_index' => 0,
                    'topics' => ['Colors', 'Family members']
                ],
                'reorder_units' => [1, 0]
            ],
            'preview_only' => true
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/curriculum-templates/{$template->id}/customize", $customizeData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'customized_structure',
                    'modifications_applied',
                    'preview_summary' => [
                        'units_count',
                        'topics_count',
                        'estimated_duration'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can validate curriculum template
     * 
     * @test
     */
    public function test_team_member_can_validate_curriculum_template()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $template = $this->createTestCurriculumTemplate();

        $validationData = [
            'check_completeness' => true,
            'check_consistency' => true,
            'check_prerequisites' => true
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/curriculum-templates/{$template->id}/validate", $validationData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'is_valid',
                    'validation_results' => [
                        'completeness' => [
                            'passed',
                            'issues'
                        ],
                        'consistency' => [
                            'passed',
                            'issues'
                        ],
                        'prerequisites' => [
                            'passed',
                            'issues'
                        ]
                    ],
                    'recommendations'
                ]
            ]);
    }

    /**
     * Test filtering templates by level
     * 
     * @test
     */
    public function test_filtering_templates_by_level()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $beginnerTemplate = $this->createTestCurriculumTemplate([
            'name' => 'Beginner Template',
            'level' => 'beginner'
        ]);

        $advancedTemplate = $this->createTestCurriculumTemplate([
            'name' => 'Advanced Template',
            'level' => 'advanced'
        ]);

        // Filter by beginner level
        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates?level=beginner");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Beginner Template');

        // Filter by advanced level
        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates?level=advanced");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Advanced Template');
    }

    /**
     * Test filtering templates by category
     * 
     * @test
     */
    public function test_filtering_templates_by_category()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $generalTemplate = $this->createTestCurriculumTemplate([
            'name' => 'General Template',
            'category' => 'general'
        ]);

        $businessTemplate = $this->createTestCurriculumTemplate([
            'name' => 'Business Template',
            'category' => 'business'
        ]);

        // Filter by general category
        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates?category=general");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'General Template');

        // Filter by business category
        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates?category=business");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Business Template');
    }

    /**
     * Test students cannot access team curriculum template endpoints
     * 
     * @test
     */
    public function test_students_cannot_access_team_curriculum_template_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['tenant']);

        $template = $this->createTestCurriculumTemplate();

        // Test various endpoints
        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates");
        $response->assertStatus(403);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates/{$template->id}");
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/curriculum-templates/{$template->id}/instantiate", [
            'learning_path_name' => 'Test Course'
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
        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates");
        $response->assertStatus(401);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates/recommendations");
        $response->assertStatus(401);
    }

    /**
     * Test nonexistent template returns 404
     * 
     * @test
     */
    public function test_nonexistent_template_returns_404()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates/99999");
        $response->assertStatus(404);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/curriculum-templates/99999/preview");
        $response->assertStatus(404);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/curriculum-templates/99999/instantiate", [
            'learning_path_name' => 'Test Course'
        ]);
        $response->assertStatus(404);
    }
}
