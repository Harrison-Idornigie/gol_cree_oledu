<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
// Media model might not exist yet, so we'll test the API endpoints without it
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class TeamMediaControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamUser;
    protected User $adminUser;
    protected User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);

        // Create users with different roles in tenant context
        $this->teamUser = $this->createTenantTeam();
        $this->adminUser = $this->createTenantAdmin();
        $this->studentUser = $this->createTenantStudent();

        // Mock the Storage facade
        Storage::fake('tenant-media');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Helper to create a mock media record in the database
     * 
     * This is a temporary solution until the Media model is fully implemented
     */
    protected function createMediaRecord(array $attributes = [])
    {
        $mediaId = Str::uuid()->toString();
        $fileName = 'test_media_' . rand(1000, 9999) . '.jpg';
        $file = UploadedFile::fake()->image($fileName);
        $fileSize = $file->getSize();

        // Store the file
        Storage::disk('tenant-media')->put($fileName, $file->getContent());

        $defaultAttrs = [
            'id' => $mediaId,
            'filename' => $fileName,
            'original_filename' => 'original_media.jpg',
            'mime_type' => 'image/jpeg',
            'size' => $fileSize,
            'path' => 'tenant-media/' . $fileName,
            'disk' => 'tenant-media',
            'type' => 'image',
            'created_by' => $this->teamUser->id,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        $attrs = array_merge($defaultAttrs, $attributes);

        // Insert directly into database since model doesn't exist yet
        return $this->runInTenantContext($this->tenant, function () use ($attrs) {
            DB::table('media')->insert($attrs);
            return (object)$attrs;
        });
    }

    /**
     * Test uploading media
     */
    public function test_upload_success()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $file = UploadedFile::fake()->image('test_image.jpg');

        $response = $this->postJson("/api/{$this->tenant->slug}/team/media/upload", [
            'file' => $file,
            'type' => 'image',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'filename',
                    'original_filename',
                    'mime_type',
                    'size',
                    'url',
                    'created_by',
                    'created_at'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Media uploaded successfully.'
            ]);

        // Verify the media was created in the database
        $this->runInTenantContext($this->tenant, function () use ($file) {
            $this->assertDatabaseHas('media', [
                'original_filename' => 'test_image.jpg',
                'mime_type' => 'image/jpeg',
                'created_by' => $this->teamUser->id,
            ]);
        });
    }

    /**
     * Test getting team member's media
     */
    public function test_my_media_success()
    {
        // Create some test media for this user
        for ($i = 0; $i < 3; $i++) {
            $this->createMediaRecord();
        }

        // Also create some media for another user
        $this->createMediaRecord(['created_by' => $this->adminUser->id]);

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/media/my-media");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Media retrieved successfully.'
            ]);

        // Only media created by this user should be returned
        $responseData = json_decode($response->getContent(), true);
        $this->assertCount(3, $responseData['data']);

        foreach ($responseData['data'] as $media) {
            $this->assertEquals($this->teamUser->id, $media['created_by']);
        }
    }

    /**
     * Test deleting media
     */
    public function test_delete_success()
    {
        // Create test media
        $media = $this->createMediaRecord();

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/media/{$media->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Media deleted successfully.'
            ]);

        // Verify the media was deleted from the database
        $this->runInTenantContext($this->tenant, function () use ($media) {
            $this->assertDatabaseMissing('media', ['id' => $media->id]);
        });
    }

    /**
     * Test unauthorized deletion attempt
     */
    public function test_delete_unauthorized()
    {
        // Create test media owned by another user
        $media = $this->createMediaRecord(['created_by' => $this->adminUser->id]);

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/media/{$media->id}");

        $response->assertStatus(403);
    }

    /**
     * Test student cannot access team media endpoints
     */
    public function test_student_cannot_access()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/media/my-media");

        $response->assertStatus(403);
    }

    /**
     * Test upload validation
     */
    public function test_upload_validation()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        // No file provided
        $response = $this->postJson("/api/{$this->tenant->slug}/team/media/upload", [
            'type' => 'image',
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);

        // Invalid file type
        $file = UploadedFile::fake()->create('test.exe', 1000);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/media/upload", [
            'file' => $file,
            'type' => 'image',
        ]);

        $response->assertStatus(400);
    }

    /**
     * Helper method to initialize tenant context
     */
    private function initializeTenantContext(Tenant $tenant): void
    {
        // The tenant is already initialized and seeded in createTestTenant
        // This method is kept for compatibility but not needed with enhanced trait
    }

    /**
     * Helper method to create tenant admin
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

    /**
     * Helper method to create tenant team member
     */
    private function createTenantTeam(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'team@test.com',
                'membership_type' => 'team',
                'email_verified_at' => now(),
            ]);
        });
    }

    /**
     * Helper method to create tenant student
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
}
