<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\GuideBookEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TeamGuideBookEntryControllerTest extends TenantTestCase
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

        // Initialize tenant context for the test
        $this->initializeTenantContext($this->tenant);

        // Create test users using proper helper methods
        $this->teamMember = $this->createTenantTeam();
        $this->studentUser = $this->createTenantStudent();

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

    protected function createTestGuideEntry(array $attributes = []): GuideBookEntry
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return GuideBookEntry::create(array_merge([
                'title' => 'Spanish Grammar Basics',
                'content' => 'This guide covers the basic grammar rules of Spanish...',
                'category' => 'grammar',
                'language_id' => $this->language->id,
                'order_index' => 1,
                'status' => 'published',
                'created_by' => $this->teamMember->id,
            ], $attributes));
        });
    }

    /**
     * Test team member can list guide entries
     * 
     * 
     */    public function test_team_member_can_list_guide_entries()
    {
        $this->runInTenantContext($this->tenant, function () {
            Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

            $entry1 = $this->createTestGuideEntry(['title' => 'Grammar Guide']);
            $entry2 = $this->createTestGuideEntry(['title' => 'Pronunciation Guide', 'order_index' => 2]);

            $response = $this->getJson("/api/{$this->tenant->slug}/team/guide-entries");

            $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'current_page',
                        'data' => [
                            '*' => [
                                'id',
                                'title',
                                'slug',
                                'content',
                                'category',
                                'status',
                                'created_at',
                                'updated_at',
                            ]
                        ],
                        'per_page',
                        'total',
                    ]
                ]);

            // Check that our created entries are in the response
            $entries = $response->json('data.data');
            $titles = collect($entries)->pluck('title')->toArray();
            $this->assertContains('Grammar Guide', $titles);
            $this->assertContains('Pronunciation Guide', $titles);
        });
    }

    /**
     * Test team member can create guide entry
     * 
     * 
     */
    public function test_team_member_can_create_guide_entry()
    {
        $this->runInTenantContext($this->tenant, function () {
            Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

            $entryData = [
                'title' => 'New Grammar Guide',
                'content' => 'This is a comprehensive guide to Spanish grammar...',
                'category' => 'grammar',
                'language_id' => $this->language->id,
                'order_index' => 1,
            ];

            $response = $this->postJson("/api/{$this->tenant->slug}/team/guide-entries", $entryData);

            $response->assertStatus(201)
                ->assertJsonPath('data.title', 'New Grammar Guide')
                ->assertJsonPath('data.content', 'This is a comprehensive guide to Spanish grammar...')
                ->assertJsonPath('data.category', 'grammar')
                ->assertJsonPath('data.language_id', $this->language->id)
                ->assertJsonPath('data.order_index', 1)
                ->assertJsonPath('data.status', 'draft')
                ->assertJsonPath('data.created_by', $this->teamMember->id);

            $this->assertDatabaseHas('guide_book_entries', [
                'title' => 'New Grammar Guide',
                'content' => 'This is a comprehensive guide to Spanish grammar...',
                'category' => 'grammar',
                'language_id' => $this->language->id,
            ]);
        });
    }

    /**
     * Test guide entry creation validation
     * 
     * 
     */
    public function test_guide_entry_creation_validation()
    {
        $this->runInTenantContext($this->tenant, function () {
            Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

            // Missing required fields
            $response = $this->postJson("/api/{$this->tenant->slug}/team/guide-entries", []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'content', 'category']);

            // Invalid language
            $response = $this->postJson("/api/{$this->tenant->slug}/team/guide-entries", [
                'title' => 'Test Guide',
                'content' => 'Test content',
                'category' => 'grammar',
                'language_id' => 99999,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['language_id']);

            // Invalid category
            $response = $this->postJson("/api/{$this->tenant->slug}/team/guide-entries", [
                'title' => 'Test Guide',
                'content' => 'Test content',
                'category' => 'invalid_category',
                'language_id' => $this->language->id,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['category']);
        });
    }

    /**
     * Test team member can show guide entry
     * 
     * 
     */
    public function test_team_member_can_show_guide_entry()
    {
        $this->runInTenantContext($this->tenant, function () {
            Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

            $entry = $this->createTestGuideEntry();

            $response = $this->getJson("/api/{$this->tenant->slug}/team/guide-entries/{$entry->id}");

            $response->assertStatus(200)
                ->assertJsonPath('data.id', $entry->id)
                ->assertJsonPath('data.title', $entry->title)
                ->assertJsonPath('data.content', $entry->content)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'title',
                        'content',
                        'category',
                        'language_id',
                        'order_index',
                        'status',
                        'tags',
                        'created_by',
                        'created_at',
                        'updated_at'
                    ]
                ]);
        });
    }

    /**
     * Test team member can update guide entry
     * 
     * 
     */
    public function test_team_member_can_update_guide_entry()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $entry = $this->createTestGuideEntry();

        $updateData = [
            'title' => 'Updated Guide Title',
            'content' => 'Updated guide content',
            'category' => 'pronunciation',
            'order_index' => 5,
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/guide-entries/{$entry->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Guide Title')
            ->assertJsonPath('data.content', 'Updated guide content')
            ->assertJsonPath('data.category', 'pronunciation')
            ->assertJsonPath('data.order_index', 5);

        $this->runInTenantContext($this->tenant, function () use ($entry) {
            $this->assertDatabaseHas('guide_book_entries', [
                'id' => $entry->id,
                'title' => 'Updated Guide Title',
                'content' => 'Updated guide content',
                'category' => 'pronunciation',
                'order_index' => 5,
            ]);
        });
    }

    /**
     * Test team member can delete guide entry
     * 
     * 
     */
    public function test_team_member_can_delete_guide_entry()
    {
        $this->runInTenantContext($this->tenant, function () {
            Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

            $entry = $this->createTestGuideEntry();

            $response = $this->deleteJson("/api/{$this->tenant->slug}/team/guide-entries/{$entry->id}");

            $response->assertStatus(204);

            $this->assertDatabaseMissing('guide_book_entries', [
                'id' => $entry->id,
            ]);
        });
    }

    /**
     * Test filtering guide entries by category
     * 
     * 
     */
    public function test_filtering_guide_entries_by_category()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $grammarEntry = $this->createTestGuideEntry([
            'title' => 'Grammar Guide',
            'category' => 'grammar'
        ]);

        $pronunciationEntry = $this->createTestGuideEntry([
            'title' => 'Pronunciation Guide',
            'category' => 'pronunciation'
        ]);

        // Filter by grammar category
        $response = $this->getJson("/api/{$this->tenant->slug}/team/guide-entries?category=grammar");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Grammar Guide');

        // Filter by pronunciation category
        $response = $this->getJson("/api/{$this->tenant->slug}/team/guide-entries?category=pronunciation");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Pronunciation Guide');
    }

    /**
     * Test filtering guide entries by language
     * 
     * 
     */
    public function test_filtering_guide_entries_by_language()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Create another language
        $frenchLanguage = $this->runInTenantContext($this->tenant, function () {
            return Language::create([
                'name' => 'French',
                'code' => 'fr',
                'native_name' => 'Français',
                'direction' => 'ltr',
                'status' => 'active',
            ]);
        });

        $spanishEntry = $this->createTestGuideEntry([
            'title' => 'Spanish Guide',
            'language_id' => $this->language->id
        ]);

        $frenchEntry = $this->createTestGuideEntry([
            'title' => 'French Guide',
            'language_id' => $frenchLanguage->id
        ]);

        // Filter by Spanish
        $response = $this->getJson("/api/{$this->tenant->slug}/team/guide-entries?language_id={$this->language->id}");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Spanish Guide');

        // Filter by French
        $response = $this->getJson("/api/{$this->tenant->slug}/team/guide-entries?language_id={$frenchLanguage->id}");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'French Guide');
    }

    /**
     * Test searching guide entries
     * 
     * 
     */
    public function test_searching_guide_entries()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $entry1 = $this->createTestGuideEntry(['title' => 'Grammar Basics']);
        $entry2 = $this->createTestGuideEntry(['title' => 'Grammar Advanced']);
        $entry3 = $this->createTestGuideEntry(['title' => 'Pronunciation Guide']);

        // Search for "grammar"
        $response = $this->getJson("/api/{$this->tenant->slug}/team/guide-entries?search=grammar");
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Search for exact title
        $response = $this->getJson("/api/{$this->tenant->slug}/team/guide-entries?search=Pronunciation");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Pronunciation Guide');
    }

    /**
     * Test students cannot access team guide entry endpoints
     * 
     * 
     */
    public function test_students_cannot_access_team_guide_entry_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['tenant']);

        $entry = $this->createTestGuideEntry();

        // Test various endpoints
        $response = $this->getJson("/api/{$this->tenant->slug}/team/guide-entries");
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/guide-entries", [
            'title' => 'Test Guide',
            'content' => 'Test content',
            'category' => 'grammar',
        ]);
        $response->assertStatus(403);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/guide-entries/{$entry->id}", [
            'title' => 'Updated Title',
        ]);
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/guide-entries/{$entry->id}");
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access is blocked
     * 
     * 
     */
    public function test_unauthenticated_access_blocked()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/team/guide-entries");
        $response->assertStatus(401);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/guide-entries", [
            'title' => 'Test Guide',
            'content' => 'Test content',
            'category' => 'grammar',
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test nonexistent guide entry returns 404
     * 
     * 
     */
    public function test_nonexistent_guide_entry_returns_404()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/guide-entries/99999");
        $response->assertStatus(404);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/guide-entries/99999", [
            'title' => 'Updated Title',
        ]);
        $response->assertStatus(404);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/guide-entries/99999");
        $response->assertStatus(404);
    }

    /**
     * Test valid categories are accepted
     * 
     * 
     */
    public function test_valid_categories_are_accepted()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $validCategories = ['grammar', 'pronunciation', 'vocabulary', 'culture', 'conversation', 'writing'];

        foreach ($validCategories as $category) {
            $entryData = [
                'title' => "Test {$category} Guide",
                'content' => "Content for {$category}",
                'category' => $category,
                'language_id' => $this->language->id,
            ];

            $response = $this->postJson("/api/{$this->tenant->slug}/team/guide-entries", $entryData);
            $response->assertStatus(201);
        }
    }

    /**
     * Test guide entry status transitions
     * 
     * 
     */
    public function test_guide_entry_status_transitions()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $entry = $this->createTestGuideEntry(['status' => 'draft']);

        // Update status to published
        $updateData = ['status' => 'published'];
        $response = $this->putJson("/api/{$this->tenant->slug}/team/guide-entries/{$entry->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $this->runInTenantContext($this->tenant, function () use ($entry) {
            $this->assertDatabaseHas('guide_book_entries', [
                'id' => $entry->id,
                'status' => 'published',
            ]);
        });
    }
}
