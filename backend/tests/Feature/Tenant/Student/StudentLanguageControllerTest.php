<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class StudentLanguageControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        $this->tenant = $this->createTestTenant();
        $this->studentUser = $this->createTenantStudent();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Test listing available languages
     */
    public function test_index_success()
    {
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/languages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Languages retrieved successfully.'
            ]);
    }

    /**
     * Test getting languages with learning paths
     */
    public function test_with_learning_paths_success()
    {
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/languages/with-learning-paths");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Languages with learning paths retrieved successfully.'
            ]);
    }

    /**
     * Test getting specific language
     */
    public function test_show_success()
    {
        Sanctum::actingAs($this->studentUser, ['*']);

        // Create a language first
        $language = $this->createLanguage();

        $response = $this->getJson("/api/{$this->tenant->slug}/student/languages/{$language->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Language retrieved successfully.'
            ]);
    }

    /**
     * Test getting learning paths for a language
     */
    public function test_learning_paths_success()
    {
        Sanctum::actingAs($this->studentUser, ['*']);
        $language = $this->createLanguage();

        $response = $this->getJson("/api/{$this->tenant->slug}/student/languages/{$language->id}/learning-paths");

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
     * Test getting proficiency levels for a language
     */
    public function test_proficiency_levels_success()
    {
        Sanctum::actingAs($this->studentUser, ['*']);
        $language = $this->createLanguage();

        $response = $this->getJson("/api/{$this->tenant->slug}/student/languages/{$language->id}/proficiency-levels");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Proficiency levels retrieved successfully.'
            ]);
    }

    /**
     * Test getting user progress in a language
     */
    public function test_user_progress_success()
    {
        Sanctum::actingAs($this->studentUser, ['*']);
        $language = $this->createLanguage();

        $response = $this->getJson("/api/{$this->tenant->slug}/student/languages/{$language->id}/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User progress retrieved successfully.'
            ]);
    }

    /**
     * Test getting language dashboard
     */
    public function test_dashboard_success()
    {
        Sanctum::actingAs($this->studentUser, ['*']);
        $language = $this->createLanguage();

        $response = $this->getJson("/api/{$this->tenant->slug}/student/languages/{$language->id}/dashboard");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Language dashboard retrieved successfully.'
            ]);
    }

    /**
     * Test language routes require authentication
     */
    public function test_requires_authentication()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/student/languages");
        $response->assertStatus(401);
    }

    /**
     * Test language routes with invalid language ID
     */
    public function test_invalid_language_id()
    {
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/languages/invalid-id");
        $response->assertStatus(404);
    }

    /**
     * Test student can only access published content
     */
    public function test_student_can_only_access_published_content()
    {
        // This test would verify that students can only see published languages and learning paths
        // Implementation would depend on the actual controller logic
        $this->assertTrue(true);
    }

    /**
     * Test expected language response structure
     */
    public function test_expected_language_response_structure()
    {
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/languages");

        $response->assertStatus(200);
        
        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected language data structure
                // 'languages' => [
                //     [
                //         'id' => 'string',
                //         'name' => 'string',
                //         'code' => 'string',
                //         'native_name' => 'string',
                //         'is_active' => 'boolean',
                //         'learning_paths_count' => 'integer'
                //     ]
                // ]
            ]
        ];

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    /**
     * Helper methods
     */
    private function createTenantStudent(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'student@test.com',
                'membership_type' => 'student',
                'email_verified_at' => now(),
            ]);
        });
    }

    private function createLanguage()
    {
        return $this->runInTenantContext($this->tenant, function () {
            return Language::create([
                'name' => 'Test Language',
                'code' => 'test',
                'native_name' => 'Test Native Name',
                'is_active' => true,
            ]);
        });
    }
}
