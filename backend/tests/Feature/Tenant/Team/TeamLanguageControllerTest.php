<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class TeamLanguageControllerTest extends TenantTestCase
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
        
        // Create users with different roles in tenant context
        $this->teamUser = $this->createTenantTeamMember();
        $this->adminUser = $this->createTenantAdmin();
        $this->studentUser = $this->createTenantStudent();
    }
    
    /**
     * Helper to initialize tenant context
     */
    protected function initializeTenantContext(Tenant $tenant)
    {
        return $this->runInTenantContext($tenant, function () {
            // Additional tenant initialization if needed
        });
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
            
            // Assign team role if roles table exists
            try {
                if (class_exists('Spatie\\Permission\\Models\\Role')) {
                    $user->assignRole('team');
                } else {
                    // Fallback: direct DB insert to user_permissions
                    if (\Illuminate\Support\Facades\Schema::hasTable('user_permissions')) {
                        \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
                            'id' => (string) Str::uuid(),
                            'user_id' => $user->id,
                            'permission' => 'team',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Role assignment might fail if tables don't exist yet
            }
            
            return $user;
        });
    }
    
    /**
     * Helper to create a tenant admin
     */
    protected function createTenantAdmin()
    {
        return $this->runInTenantContext($this->tenant, function () {
            $user = User::create([
                'name' => 'Admin User',
                'email' => 'admin_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
            
            // Assign admin role if roles table exists
            try {
                if (class_exists('Spatie\\Permission\\Models\\Role')) {
                    $user->assignRole(['admin', 'tenant-admin']);
                } else {
                    // Fallback: direct DB insert to user_permissions
                    if (\Illuminate\Support\Facades\Schema::hasTable('user_permissions')) {
                        \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
                            'id' => (string) Str::uuid(),
                            'user_id' => $user->id,
                            'permission' => 'admin',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
                            'id' => (string) Str::uuid(),
                            'user_id' => $user->id,
                            'permission' => 'tenant-admin',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
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
            
            // Assign student role if roles table exists
            try {
                if (class_exists('Spatie\\Permission\\Models\\Role')) {
                    $user->assignRole('student');
                } else {
                    // Fallback: direct DB insert to user_permissions
                    if (\Illuminate\Support\Facades\Schema::hasTable('user_permissions')) {
                        \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
                            'id' => (string) Str::uuid(),
                            'user_id' => $user->id,
                            'permission' => 'student',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Role assignment might fail if tables don't exist yet
            }
            
            return $user;
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Helper to create a test language
     */
    protected function createTestLanguage(array $attributes = [])
    {
        $defaultAttrs = [
            'name' => 'Test Language ' . Str::random(4),
            'code' => 'tl' . Str::random(2),
            'native_name' => 'Test Native Name ' . Str::random(4),
            'is_active' => true,
            'created_by' => $this->teamUser->id,
        ];
        
        return $this->runInTenantContext($this->tenant, function () use ($defaultAttrs, $attributes) {
            return Language::create(array_merge($defaultAttrs, $attributes));
        });
    }

    /** @test */
    public function team_member_can_view_languages_list()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, [], 'tenant');

        // Create test languages
        $language1 = $this->createTestLanguage();
        $language2 = $this->createTestLanguage(['name' => 'Another Language']);

        // API call to get languages
        $response = $this->getJson("/api/{$this->tenant->slug}/team/languages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'native_name',
                        'is_active',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'name' => $language1->name
            ])
            ->assertJsonFragment([
                'name' => $language2->name
            ]);
    }

    /** @test */
    public function student_cannot_access_team_language_endpoints()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get languages should be forbidden
        $response = $this->getJson("/api/{$this->tenant->slug}/team/languages");
        $response->assertStatus(403);
    }

    /** @test */
    public function team_member_can_create_language()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, [], 'tenant');

        // Data for new language
        $languageData = [
            'name' => 'New Test Language',
            'code' => 'ntl',
            'native_name' => 'New Test Native Name',
            'is_active' => true
        ];

        // API call to create language
        $response = $this->postJson("/api/{$this->tenant->slug}/team/languages", $languageData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'code',
                    'native_name',
                    'is_active',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'name' => 'New Test Language',
                'code' => 'ntl'
            ]);

        // Verify language was created in database
        $this->assertTenantHasData($this->tenant, 'languages', [
            'name' => 'New Test Language',
            'code' => 'ntl'
        ]);
    }

    /** @test */
    public function validation_errors_are_returned_when_creating_language_with_invalid_data()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, [], 'tenant');

        // Create a language to test unique validation
        $existingLanguage = $this->createTestLanguage([
            'code' => 'ext'
        ]);

        // Data with validation errors
        $invalidData = [
            'name' => '',  // Empty name
            'code' => 'ext', // Duplicate code
            'is_active' => 'not-a-boolean' // Invalid boolean
        ];

        // API call with invalid data
        $response = $this->postJson("/api/{$this->tenant->slug}/team/languages", $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code', 'is_active']);
    }

    /** @test */
    public function team_member_can_view_language_details()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, [], 'tenant');

        // Create test language
        $language = $this->createTestLanguage();

        // API call to get language details
        $response = $this->getJson("/api/{$this->tenant->slug}/team/languages/{$language->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'code',
                    'native_name',
                    'is_active',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'id' => $language->id,
                'name' => $language->name,
                'code' => $language->code
            ]);
    }

    /** @test */
    public function team_member_can_update_language()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, [], 'tenant');

        // Create test language
        $language = $this->createTestLanguage();

        // Update data
        $updateData = [
            'name' => 'Updated Language Name',
            'native_name' => 'Updated Native Name',
            'is_active' => false
        ];

        // API call to update language
        $response = $this->putJson("/api/{$this->tenant->slug}/team/languages/{$language->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'code',
                    'native_name',
                    'is_active',
                    'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'id' => $language->id,
                'name' => 'Updated Language Name',
                'native_name' => 'Updated Native Name',
                'is_active' => false
            ]);

        // Verify language was updated in database
        $this->assertTenantHasData($this->tenant, 'languages', [
            'id' => $language->id,
            'name' => 'Updated Language Name',
            'native_name' => 'Updated Native Name',
            'is_active' => 0
        ]);
    }

    /** @test */
    public function team_member_can_update_language_status()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, [], 'tenant');

        // Create test language with active status
        $language = $this->createTestLanguage(['is_active' => true]);

        // API call to update status to inactive
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/languages/{$language->id}/status", [
            'is_active' => false
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'is_active' => false
            ]);

        // Verify status was updated in database
        $this->assertTenantHasData($this->tenant, 'languages', [
            'id' => $language->id,
            'is_active' => 0
        ]);
    }

    /** @test */
    public function team_member_can_create_language_pair()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, [], 'tenant');

        // Create source and target languages
        $sourceLanguage = $this->createTestLanguage();
        $targetLanguage = $this->createTestLanguage();

        // API call to create language pair
        $response = $this->postJson("/api/{$this->tenant->slug}/team/languages/pairs", [
            'source_language_id' => $sourceLanguage->id,
            'target_language_id' => $targetLanguage->id,
            'is_active' => true
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'source_language_id',
                    'target_language_id',
                    'is_active',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'source_language_id' => $sourceLanguage->id,
                'target_language_id' => $targetLanguage->id,
                'is_active' => true
            ]);

        // Verify pair was created in database
        $this->assertTenantHasData($this->tenant, 'language_pairs', [
            'source_language_id' => $sourceLanguage->id,
            'target_language_id' => $targetLanguage->id,
            'is_active' => 1
        ]);
    }

    /** @test */
    public function team_member_can_delete_language_pair()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, [], 'tenant');

        // Create source and target languages
        $sourceLanguage = $this->createTestLanguage();
        $targetLanguage = $this->createTestLanguage();

        // Create language pair in database
        $this->runInTenantContext($this->tenant, function () use ($sourceLanguage, $targetLanguage) {
            // Check if language_pairs table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('language_pairs')) {
                \Illuminate\Support\Facades\Schema::create('language_pairs', function ($table) {
                    $table->uuid('id')->primary();
                    $table->uuid('source_language_id');
                    $table->uuid('target_language_id');
                    $table->boolean('is_active')->default(true);
                    $table->timestamps();
                    
                    $table->foreign('source_language_id')->references('id')->on('languages');
                    $table->foreign('target_language_id')->references('id')->on('languages');
                });
            }
            
            return \Illuminate\Support\Facades\DB::table('language_pairs')->insert([
                'id' => (string) Str::uuid(),
                'source_language_id' => $sourceLanguage->id,
                'target_language_id' => $targetLanguage->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // API call to delete language pair
        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/languages/pairs/{$sourceLanguage->id}/{$targetLanguage->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Language pair deleted successfully'
            ]);

        // Verify language pair was deleted or is marked as inactive in database
        $this->runInTenantContext($this->tenant, function () use ($sourceLanguage, $targetLanguage) {
            $pair = \Illuminate\Support\Facades\DB::table('language_pairs')
                ->where('source_language_id', $sourceLanguage->id)
                ->where('target_language_id', $targetLanguage->id)
                ->first();
            
            $this->assertNull($pair);
        });
    }

    /** @test */
    public function team_member_can_update_language_pair_status()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, [], 'tenant');

        // Create source and target languages
        $sourceLanguage = $this->createTestLanguage();
        $targetLanguage = $this->createTestLanguage();

        // Create active language pair in database
        $this->runInTenantContext($this->tenant, function () use ($sourceLanguage, $targetLanguage) {
            // Check if language_pairs table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('language_pairs')) {
                \Illuminate\Support\Facades\Schema::create('language_pairs', function ($table) {
                    $table->uuid('id')->primary();
                    $table->uuid('source_language_id');
                    $table->uuid('target_language_id');
                    $table->boolean('is_active')->default(true);
                    $table->timestamps();
                    
                    $table->foreign('source_language_id')->references('id')->on('languages');
                    $table->foreign('target_language_id')->references('id')->on('languages');
                });
            }
            
            return \Illuminate\Support\Facades\DB::table('language_pairs')->insert([
                'id' => (string) Str::uuid(),
                'source_language_id' => $sourceLanguage->id,
                'target_language_id' => $targetLanguage->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // API call to update language pair status
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/languages/pairs/{$sourceLanguage->id}/{$targetLanguage->id}/status", [
            'is_active' => false
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'is_active' => false
            ]);

        // Verify language pair was updated in database
        $this->runInTenantContext($this->tenant, function () use ($sourceLanguage, $targetLanguage) {
            $pair = \Illuminate\Support\Facades\DB::table('language_pairs')
                ->where('source_language_id', $sourceLanguage->id)
                ->where('target_language_id', $targetLanguage->id)
                ->first();
            
            $this->assertFalse((bool) $pair->is_active);
        });
    }
}
