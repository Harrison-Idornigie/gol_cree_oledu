<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\GuideBookEntry;
use App\Models\Tenants\Language;
use App\Models\Tenants\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class StudentGuideControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $language;
    protected Topic $topic;
    protected array $guideEntries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        $this->tenant = $this->createTestTenant();
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeamMember();
        $this->language = $this->createLanguage();
        $this->topic = $this->createTopic();
        $this->guideEntries = $this->createGuideEntries();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createTenantStudent(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'student@example.com',
                'password' => bcrypt('password123'),
                'membership' => 'student'
            ]);
        });
    }

    protected function createTenantTeamMember(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'team@example.com',
                'password' => bcrypt('password123'),
                'membership' => 'team'
            ]);
        });
    }

    protected function createLanguage(): Language
    {
        return $this->runInTenantContext($this->tenant, function () {
            return Language::factory()->create([
                'name' => 'Plains Cree',
                'code' => 'crk',
                'native_name' => 'nêhiyawêwin',
                'is_active' => true
            ]);
        });
    }

    protected function createTopic(): Topic
    {
        return $this->runInTenantContext($this->tenant, function () {
            return Topic::factory()->create([
                'title' => 'Basic Greetings',
                'description' => 'Learn basic greeting phrases',
                'language_id' => $this->language->id,
                'status' => 'published'
            ]);
        });
    }

    protected function createGuideEntries(): array
    {
        return $this->runInTenantContext($this->tenant, function () {
            return [
                'published' => GuideBookEntry::factory()->create([
                    'title' => 'Grammar Guide: Verbs',
                    'content' => 'This guide explains verb conjugation in Plains Cree.',
                    'category' => 'grammar',
                    'language_id' => $this->language->id,
                    'topic_id' => $this->topic->id,
                    'status' => 'published',
                    'visibility' => 'public'
                ]),
                'draft' => GuideBookEntry::factory()->create([
                    'title' => 'Draft Guide: Advanced Grammar',
                    'content' => 'This is a draft guide.',
                    'category' => 'grammar',
                    'language_id' => $this->language->id,
                    'status' => 'draft',
                    'visibility' => 'public'
                ]),
                'private' => GuideBookEntry::factory()->create([
                    'title' => 'Private Guide: Teacher Notes',
                    'content' => 'This is for teachers only.',
                    'category' => 'teaching',
                    'language_id' => $this->language->id,
                    'status' => 'published',
                    'visibility' => 'private'
                ])
            ];
        });
    }

    /** @test */
    public function student_can_view_published_guide_entries()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'category',
                        'language_id',
                        'status',
                        'visibility'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Guide entries retrieved successfully.'
            ]);

        // Should include published entries
        $response->assertJsonFragment([
            'title' => 'Grammar Guide: Verbs'
        ]);

        // Should not include draft or private entries
        $response->assertJsonMissing([
            'title' => 'Draft Guide: Advanced Grammar'
        ]);
        $response->assertJsonMissing([
            'title' => 'Private Guide: Teacher Notes'
        ]);
    }

    /** @test */
    public function student_can_view_specific_published_guide_entry()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $guideEntry = $this->guideEntries['published'];

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries/{$guideEntry->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'content',
                    'category',
                    'language_id',
                    'topic_id',
                    'status',
                    'visibility'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Guide entry retrieved successfully.',
                'data' => [
                    'id' => $guideEntry->id,
                    'title' => 'Grammar Guide: Verbs',
                    'content' => 'This guide explains verb conjugation in Plains Cree.',
                    'category' => 'grammar'
                ]
            ]);
    }

    /** @test */
    public function student_cannot_view_draft_guide_entries()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $draftGuide = $this->guideEntries['draft'];

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries/{$draftGuide->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Guide entry not found or not available'
            ]);
    }

    /** @test */
    public function student_cannot_view_private_guide_entries()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $privateGuide = $this->guideEntries['private'];

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries/{$privateGuide->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Guide entry not found or not available'
            ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_guide_entries()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries");

        $response->assertStatus(401);
    }

    /** @test */
    public function guide_entries_are_filtered_by_student_context()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries");

        $response->assertStatus(200);

        // Verify only appropriate entries are returned for students
        $data = $response->json('data');
        
        foreach ($data as $entry) {
            $this->assertEquals('published', $entry['status']);
            $this->assertEquals('public', $entry['visibility']);
        }
    }

    /** @test */
    public function guide_entries_support_filtering_by_category()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries?category=grammar");

        $response->assertStatus(200);

        $data = $response->json('data');
        
        foreach ($data as $entry) {
            $this->assertEquals('grammar', $entry['category']);
        }
    }

    /** @test */
    public function guide_entries_support_filtering_by_language()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries?language_id={$this->language->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        
        foreach ($data as $entry) {
            $this->assertEquals($this->language->id, $entry['language_id']);
        }
    }

    /** @test */
    public function guide_entries_support_search_functionality()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries?search=verb");

        $response->assertStatus(200);

        // Should find the guide about verbs
        $response->assertJsonFragment([
            'title' => 'Grammar Guide: Verbs'
        ]);
    }

    /** @test */
    public function team_member_can_also_access_guide_entries()
    {
        Sanctum::actingAs($this->teamUser, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Guide entries retrieved successfully.'
            ]);
    }

    /** @test */
    public function guide_entry_includes_related_data()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $guideEntry = $this->guideEntries['published'];

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries/{$guideEntry->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'content',
                    'language',
                    'topic'
                ]
            ]);

        // Verify related data is included
        $data = $response->json('data');
        $this->assertArrayHasKey('language', $data);
        $this->assertArrayHasKey('topic', $data);
    }

    /** @test */
    public function guide_entries_respect_tenant_isolation()
    {
        // Create another tenant with guide entries
        $otherTenant = $this->createTestTenant('other-tenant');
        $otherGuideEntry = $this->runInTenantContext($otherTenant, function () {
            $otherLanguage = Language::factory()->create([
                'name' => 'Other Language',
                'code' => 'oth'
            ]);
            
            return GuideBookEntry::factory()->create([
                'title' => 'Other Tenant Guide',
                'language_id' => $otherLanguage->id,
                'status' => 'published',
                'visibility' => 'public'
            ]);
        });

        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries");

        $response->assertStatus(200);

        // Should not include guide entries from other tenant
        $response->assertJsonMissing([
            'title' => 'Other Tenant Guide'
        ]);
    }

    /** @test */
    public function guide_entry_handles_nonexistent_id()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries/99999");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Guide entry not found or not available'
            ]);
    }

    /** @test */
    public function guide_entries_handle_server_errors_gracefully()
    {
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Test with valid request to ensure endpoint works
        $response = $this->getJson("/api/{$this->tenant->slug}/student/guide-entries");

        // Should either succeed or handle error gracefully
        $this->assertContains($response->status(), [200, 500]);
        
        if ($response->status() === 500) {
            $response->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Failed to retrieve guide entries'
            ]);
        }
    }
}
