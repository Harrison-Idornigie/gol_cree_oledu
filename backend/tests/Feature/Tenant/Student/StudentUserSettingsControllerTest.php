<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class StudentUserSettingsControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $englishLanguage;
    protected Language $frenchLanguage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);

        // Create users
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeam();

        // Create languages
        $this->englishLanguage = $this->createLanguage(['code' => 'en', 'name' => 'English']);
        $this->frenchLanguage = $this->createLanguage(['code' => 'fr', 'name' => 'French']);
    }

    /**  */
    public function test_student_can_get_user_settings()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/settings");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'interface_language',
                    'notification_preferences',
                    'learning_preferences',
                    'privacy_settings'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User settings retrieved successfully.'
            ]);
    }

    /**  */
    public function test_student_can_update_user_settings()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $settingsData = [
            'interface_language' => 'fr',
            'notification_preferences' => [
                'email_notifications' => true,
                'push_notifications' => false,
                'lesson_reminders' => true
            ],
            'learning_preferences' => [
                'daily_goal_minutes' => 30,
                'difficulty_preference' => 'intermediate',
                'audio_autoplay' => true
            ],
            'privacy_settings' => [
                'profile_visibility' => 'private',
                'progress_sharing' => false
            ]
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/student/settings", $settingsData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'interface_language',
                    'notification_preferences',
                    'learning_preferences',
                    'privacy_settings'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User settings updated successfully.',
                'data' => [
                    'interface_language' => 'fr'
                ]
            ]);
    }

    /**  */
    public function test_student_settings_validates_interface_language()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->putJson("/api/{$this->tenant->slug}/student/settings", [
            'interface_language' => 'invalid_language_code'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['interface_language']);
    }

    /**  */
    public function test_student_settings_validates_daily_goal()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->putJson("/api/{$this->tenant->slug}/student/settings", [
            'learning_preferences' => [
                'daily_goal_minutes' => -10 // Invalid negative value
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['learning_preferences.daily_goal_minutes']);
    }

    /**  */
    public function test_student_settings_validates_difficulty_preference()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->putJson("/api/{$this->tenant->slug}/student/settings", [
            'learning_preferences' => [
                'difficulty_preference' => 'invalid_difficulty'
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['learning_preferences.difficulty_preference']);
    }

    /**  */
    public function team_members_cannot_access_student_settings()
    {
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/settings");
        $response->assertStatus(403);

        $response = $this->putJson("/api/{$this->tenant->slug}/student/settings", [
            'interface_language' => 'fr'
        ]);
        $response->assertStatus(403);
    }

    /**  */
    public function test_unauthenticated_users_cannot_access_settings()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/student/settings");
        $response->assertStatus(401);

        $response = $this->putJson("/api/{$this->tenant->slug}/student/settings", [
            'interface_language' => 'fr'
        ]);
        $response->assertStatus(401);
    }

    /**  */
    public function test_student_settings_are_tenant_isolated()
    {
        $otherTenant = $this->createTestTenant('other-tenant');

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Try to access settings using other tenant's slug
        $response = $this->getJson("/api/{$otherTenant->slug}/student/settings");
        $response->assertStatus(404);
    }

    /**  */
    public function test_student_can_partially_update_settings()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Update only notification preferences
        $response = $this->putJson("/api/{$this->tenant->slug}/student/settings", [
            'notification_preferences' => [
                'email_notifications' => false,
                'lesson_reminders' => true
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'notification_preferences' => [
                        'email_notifications' => false,
                        'lesson_reminders' => true
                    ]
                ]
            ]);
    }

    /**  */
    public function test_student_settings_persist_across_requests()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Update settings
        $this->putJson("/api/{$this->tenant->slug}/student/settings", [
            'interface_language' => 'fr',
            'learning_preferences' => [
                'daily_goal_minutes' => 45
            ]
        ]);

        // Retrieve settings to verify persistence
        $response = $this->getJson("/api/{$this->tenant->slug}/student/settings");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'interface_language' => 'fr',
                    'learning_preferences' => [
                        'daily_goal_minutes' => 45
                    ]
                ]
            ]);
    }
}
