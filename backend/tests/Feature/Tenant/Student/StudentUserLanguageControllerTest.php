<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class StudentUserLanguageControllerTest extends TenantTestCase
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
     * Setup test data for user language selection
     */
    protected function setupTestData()
    {
        return $this->runInTenantContext($this->tenant, function () {
            // Create languages for testing
            $language1Id = \Illuminate\Support\Facades\DB::table('languages')->insertGetId([
                'name' => 'French',
                'code' => 'fr',
                'native_name' => 'Français',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $language2Id = \Illuminate\Support\Facades\DB::table('languages')->insertGetId([
                'name' => 'Spanish',
                'code' => 'es',
                'native_name' => 'Español',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $language3Id = \Illuminate\Support\Facades\DB::table('languages')->insertGetId([
                'name' => 'German',
                'code' => 'de',
                'native_name' => 'Deutsch',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Add French as a selected language for the student
            \Illuminate\Support\Facades\DB::table('user_languages')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->studentUser->id,
                'language_id' => $language1Id,
                'is_primary' => true,
                'proficiency_level' => 'beginner',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return [
                'french_id' => $language1Id,
                'spanish_id' => $language2Id,
                'german_id' => $language3Id
            ];
        });
    }

    /** @test */
    public function student_can_list_selected_languages()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get selected languages
        $response = $this->getJson("/api/{$this->tenant->slug}/student/user/selected-languages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'language_id',
                        'language_name',
                        'language_code',
                        'is_primary',
                        'proficiency_level'
                    ]
                ]
            ])
            ->assertJsonCount(1, 'data') // Should have 1 language (French)
            ->assertJsonFragment([
                'language_name' => 'French',
                'language_code' => 'fr',
                'is_primary' => true,
                'proficiency_level' => 'beginner'
            ]);
    }

    /** @test */
    public function student_can_add_new_language()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to add Spanish as a selected language
        $response = $this->postJson("/api/{$this->tenant->slug}/student/user/selected-languages", [
            'language_id' => $this->testData['spanish_id'],
            'proficiency_level' => 'intermediate'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'language_id',
                    'language_name',
                    'language_code',
                    'is_primary',
                    'proficiency_level'
                ]
            ])
            ->assertJson([
                'data' => [
                    'language_id' => $this->testData['spanish_id'],
                    'language_name' => 'Spanish',
                    'language_code' => 'es',
                    'is_primary' => false, // Should not be primary since French was already set as primary
                    'proficiency_level' => 'intermediate'
                ]
            ]);

        // Verify the language was added in DB
        $this->runInTenantContext($this->tenant, function () {
            $userLanguage = \Illuminate\Support\Facades\DB::table('user_languages')
                ->where('user_id', $this->studentUser->id)
                ->where('language_id', $this->testData['spanish_id'])
                ->first();

            $this->assertNotNull($userLanguage);
            $this->assertEquals('intermediate', $userLanguage->proficiency_level);
            $this->assertEquals(0, $userLanguage->is_primary);
        });
    }

    /** @test */
    public function student_can_remove_selected_language()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to remove French from selected languages
        $response = $this->deleteJson("/api/{$this->tenant->slug}/student/user/selected-languages/{$this->testData['french_id']}");

        $response->assertStatus(200);

        // Verify the language was removed from DB
        $this->runInTenantContext($this->tenant, function () {
            $userLanguageCount = \Illuminate\Support\Facades\DB::table('user_languages')
                ->where('user_id', $this->studentUser->id)
                ->where('language_id', $this->testData['french_id'])
                ->count();

            $this->assertEquals(0, $userLanguageCount);
        });
    }

    /** @test */
    public function student_can_set_primary_language()
    {
        // First add a second language
        Sanctum::actingAs($this->studentUser, [], 'tenant');
        $this->postJson("/api/{$this->tenant->slug}/student/user/selected-languages", [
            'language_id' => $this->testData['spanish_id'],
            'proficiency_level' => 'intermediate'
        ]);

        // API call to set Spanish as primary language
        $response = $this->patchJson("/api/{$this->tenant->slug}/student/user/selected-languages/{$this->testData['spanish_id']}/set-primary");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'language_id' => $this->testData['spanish_id'],
                    'is_primary' => true
                ]
            ]);

        // Verify Spanish is now primary and French is not
        $this->runInTenantContext($this->tenant, function () {
            // Spanish should be primary
            $spanishLanguage = \Illuminate\Support\Facades\DB::table('user_languages')
                ->where('user_id', $this->studentUser->id)
                ->where('language_id', $this->testData['spanish_id'])
                ->first();

            $this->assertNotNull($spanishLanguage);
            $this->assertEquals(1, $spanishLanguage->is_primary);

            // French should not be primary
            $frenchLanguage = \Illuminate\Support\Facades\DB::table('user_languages')
                ->where('user_id', $this->studentUser->id)
                ->where('language_id', $this->testData['french_id'])
                ->first();

            $this->assertNotNull($frenchLanguage);
            $this->assertEquals(0, $frenchLanguage->is_primary);
        });
    }

    /** @test */
    public function student_cannot_add_language_that_is_already_selected()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to add French again (already selected)
        $response = $this->postJson("/api/{$this->tenant->slug}/student/user/selected-languages", [
            'language_id' => $this->testData['french_id'],
            'proficiency_level' => 'advanced'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_id']);
    }

    /** @test */
    public function student_cannot_add_invalid_language_id()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call with non-existent language ID
        $response = $this->postJson("/api/{$this->tenant->slug}/student/user/selected-languages", [
            'language_id' => 9999,
            'proficiency_level' => 'advanced'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_id']);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_language_endpoints()
    {
        // API calls without authentication
        $response = $this->getJson("/api/{$this->tenant->slug}/student/user/selected-languages");
        $response->assertStatus(401);

        $response = $this->postJson("/api/{$this->tenant->slug}/student/user/selected-languages", [
            'language_id' => $this->testData['german_id'],
            'proficiency_level' => 'beginner'
        ]);
        $response->assertStatus(401);
    }
}
