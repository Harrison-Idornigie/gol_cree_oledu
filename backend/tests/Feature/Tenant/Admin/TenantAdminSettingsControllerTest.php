<?php

namespace Tests\Feature\Tenant\Admin;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TenantAdminSettingsControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        $this->tenant = $this->createTestTenant();
        $this->adminUser = $this->createTenantAdmin();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Test getting tenant settings
     */
    public function test_get_tenant_settings_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/settings");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Tenant settings retrieved successfully.'
            ]);
    }

    /**
     * Test updating tenant settings
     */
    public function test_update_tenant_settings_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $settingsData = [
            'organization_name' => 'Updated Test Organization',
            'default_language' => 'en',
            'timezone' => 'America/Toronto',
            'enable_public_registration' => true,
            'max_users' => 1000
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/tenant-admin/settings", $settingsData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Tenant settings updated successfully.'
            ]);
    }

    /**
     * Test getting branding settings
     */
    public function test_get_branding_settings_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/settings/branding");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Branding settings retrieved successfully.'
            ]);
    }

    /**
     * Test updating branding settings
     */
    public function test_update_branding_settings_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $brandingData = [
            'logo_url' => 'https://example.com/logo.png',
            'primary_color' => '#007bff',
            'secondary_color' => '#6c757d',
            'font_family' => 'Inter, sans-serif',
            'custom_css' => '.custom { color: #333; }'
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/tenant-admin/settings/branding", $brandingData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Branding settings updated successfully.'
            ]);
    }

    /**
     * Test getting feature settings
     */
    public function test_get_feature_settings_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/settings/features");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Feature settings retrieved successfully.'
            ]);
    }

    /**
     * Test updating feature settings
     */
    public function test_update_feature_settings_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $featureData = [
            'enable_gamification' => true,
            'enable_social_features' => false,
            'enable_ai_assistance' => true,
            'enable_voice_recognition' => true,
            'enable_offline_mode' => false,
            'enable_progress_sharing' => true
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/tenant-admin/settings/features", $featureData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Feature settings updated successfully.'
            ]);
    }

    /**
     * Test settings require authentication
     */
    public function test_settings_require_authentication()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/settings");
        $response->assertStatus(401);
    }

    /**
     * Test settings require admin permission
     */
    public function test_settings_require_admin_permission()
    {
        $studentUser = $this->createTenantStudent();
        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/settings");
        $response->assertStatus(403);
    }

    /**
     * Test update settings with invalid data
     */
    public function test_update_settings_with_invalid_data()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->putJson("/api/{$this->tenant->slug}/tenant-admin/settings", [
            'max_users' => 'invalid', // Should be integer
            'timezone' => 'invalid/timezone', // Should be valid timezone
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test update branding with invalid colors
     */
    public function test_update_branding_with_invalid_colors()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->putJson("/api/{$this->tenant->slug}/tenant-admin/settings/branding", [
            'primary_color' => 'invalid-color', // Should be valid hex color
            'secondary_color' => '#gggggg', // Invalid hex color
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test expected settings response structure when implemented
     */
    public function test_expected_settings_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/settings");

        $response->assertStatus(200);

        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected settings data structure
                // 'general' => [
                //     'organization_name' => 'string',
                //     'default_language' => 'string',
                //     'timezone' => 'string'
                // ],
                // 'limits' => [
                //     'max_users' => 'integer',
                //     'storage_limit' => 'integer'
                // ],
                // 'features' => [
                //     'enable_gamification' => 'boolean',
                //     'enable_ai_assistance' => 'boolean'
                // ]
            ]
        ];

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    /**
     * Helper methods
     */
    private function createTenantAdmin(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'admin@test.com',
                'membership_type' => 'tenant-admin',
                'email_verified_at' => now(),
            ]);
        });
    }

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
}
