<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class TeamWordControllerTest extends TenantTestCase
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
        $this->initializeTenantContext($this->tenant);

        // Create users with different roles in tenant context
        $this->teamUser = $this->createTenantTeam();
        $this->adminUser = $this->createTenantAdmin();
        $this->studentUser = $this->createTenantStudent();

        // Create test language
        $this->language = $this->createLanguage();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Helper to create a test language
     */
    protected function createLanguage() {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Language::create([
                'name' => 'Test Language',
                'code' => 'tl',
                'native_name' => 'Test Language Native',
                'is_active' => true
            ]);
        });
    }

    /**
     * Helper to create a test word
     */
    protected function createWord(array $attributes = [])
    {
        $defaultAttrs = [
            'text' => 'test_word_' . rand(1000, 9999),
            'language_id' => $this->language->id,
            'status' => 'published',
            'part_of_speech' => 'noun',
            'created_by' => $this->teamUser->id,
        ];

        return $this->runInTenantContext($this->tenant, function () use ($defaultAttrs, $attributes) {
            return Word::create(array_merge($defaultAttrs, $attributes));
        });
    }

    /**
     * Test listing words (index method)
     */
    public function test_index_success()
    {
        // Create some test words
        $words = [];
        for ($i = 0; $i < 3; $i++) {
            $words[] = $this->createWord();
        }

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/words");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Words retrieved successfully.'
            ]);
    }

    /**
     * Test creating a new word (store method)
     */
    public function test_store_success()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $wordData = [
            'text' => 'new_test_word',
            'language_id' => $this->language->id,
            'part_of_speech' => 'verb',
            'status' => 'draft'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/words", $wordData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'text',
                    'language_id',
                    'part_of_speech',
                    'status',
                    'created_by',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Word created successfully.',
                'data' => [
                    'text' => 'new_test_word',
                    'language_id' => $this->language->id,
                    'part_of_speech' => 'verb',
                    'status' => 'draft'
                ]
            ]);
    }

    /**
     * Test validation failure when creating a word
     */
    public function test_store_validation_failure()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        // Missing required fields
        $wordData = [
            'text' => '' // Empty text, which should fail validation
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/words", $wordData);

        $response->assertStatus(400)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test retrieving a specific word (show method)
     */
    public function test_show_success()
    {
        // Create test word
        $word = $this->createWord();

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/words/{$word->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'text',
                    'language_id',
                    'part_of_speech',
                    'status',
                    'created_by',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Word retrieved successfully.',
                'data' => [
                    'id' => $word->id,
                    'text' => $word->text
                ]
            ]);
    }

    /**
     * Test updating a word (update method)
     */
    public function test_update_success()
    {
        // Create test word
        $word = $this->createWord();

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $updateData = [
            'text' => 'updated_word',
            'part_of_speech' => 'adjective'
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/words/{$word->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Word updated successfully.',
                'data' => [
                    'id' => $word->id,
                    'text' => 'updated_word',
                    'part_of_speech' => 'adjective'
                ]
            ]);
    }

    /**
     * Test deleting a word (destroy method)
     */
    public function test_destroy_success()
    {
        // Create test word
        $word = $this->createWord();

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/words/{$word->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Word deleted successfully.'
            ]);

        // Verify the word was actually deleted
        $this->runInTenantContext($this->tenant, function () use ($word) {
            $this->assertDatabaseMissing('words', ['id' => $word->id]);
        });
    }

    /**
     * Test bulk storing words
     */
    public function test_bulk_store_success()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $wordData = [
            'words' => [
                [
                    'text' => 'bulk_word_1',
                    'language_id' => $this->language->id,
                    'part_of_speech' => 'noun',
                    'status' => 'draft'
                ],
                [
                    'text' => 'bulk_word_2',
                    'language_id' => $this->language->id,
                    'part_of_speech' => 'verb',
                    'status' => 'draft'
                ]
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/words/bulk", $wordData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'text',
                        'language_id'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Words created successfully.'
            ]);

        // Verify the words were actually created
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('words', ['text' => 'bulk_word_1']);
            $this->assertDatabaseHas('words', ['text' => 'bulk_word_2']);
        });
    }

    /**
     * Test bulk update of words
     */
    public function test_bulk_update_success()
    {
        // Create test words
        $word1 = $this->createWord(['text' => 'bulk_update_1']);
        $word2 = $this->createWord(['text' => 'bulk_update_2']);

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $updateData = [
            'words' => [
                [
                    'id' => $word1->id,
                    'text' => 'bulk_updated_1',
                    'part_of_speech' => 'verb'
                ],
                [
                    'id' => $word2->id,
                    'text' => 'bulk_updated_2',
                    'part_of_speech' => 'adverb'
                ]
            ]
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/words/bulk", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Words updated successfully.'
            ]);

        // Verify the updates were applied
        $this->runInTenantContext($this->tenant, function () use ($word1, $word2) {
            $this->assertDatabaseHas('words', [
                'id' => $word1->id,
                'text' => 'bulk_updated_1',
                'part_of_speech' => 'verb'
            ]);
            $this->assertDatabaseHas('words', [
                'id' => $word2->id,
                'text' => 'bulk_updated_2',
                'part_of_speech' => 'adverb'
            ]);
        });
    }

    /**
     * Test bulk delete of words
     */
    public function test_bulk_delete_success()
    {
        // Create test words
        $word1 = $this->createWord(['text' => 'bulk_delete_1']);
        $word2 = $this->createWord(['text' => 'bulk_delete_2']);

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $deleteData = [
            'word_ids' => [$word1->id, $word2->id]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/words/bulk-delete", $deleteData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Words deleted successfully.'
            ]);

        // Verify the words were actually deleted
        $this->runInTenantContext($this->tenant, function () use ($word1, $word2) {
            $this->assertDatabaseMissing('words', ['id' => $word1->id]);
            $this->assertDatabaseMissing('words', ['id' => $word2->id]);
        });
    }

    /**
     * Test adding a translation to a word
     */
    public function test_add_translation_success()
    {
        // Create test word
        $word = $this->createWord();

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        // Create a second language for translation
        $targetLanguage = $this->runInTenantContext($this->tenant, function () {
            return Language::create([
                'name' => 'Target Language',
                'code' => 'tg',
                'native_name' => 'Target Language Native',
                'is_active' => true
            ]);
        });

        $translationData = [
            'text' => 'translation_text',
            'language_id' => $targetLanguage->id,
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/words/{$word->id}/translations", $translationData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Translation added successfully.'
            ]);
    }

    /**
     * Test student user cannot access team endpoints
     */
    public function test_student_cannot_access()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/words");

        $response->assertStatus(403);
    }

    /**
     * Test file upload for word audio
     */
    public function test_upload_audio_success()
    {
        // Create test word
        $word = $this->createWord();

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        // Mock the Storage facade
        Storage::fake('public');

        $file = UploadedFile::fake()->create('audio.mp3', 100);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/words/{$word->id}/audio", [
            'audio_file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'audio_url'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Audio uploaded successfully.'
            ]);
    }

    /**
     * Test exporting words
     */
    public function test_export_success()
    {
        // Create some test words
        for ($i = 0; $i < 3; $i++) {
            $this->createWord(['text' => "export_word_{$i}"]);
        }

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/words/export?language_id={$this->language->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Words exported successfully.'
            ]);
    }

    /**
     * Test getting available words for exercise creation
     */
    public function test_get_available_words_success()
    {
        // Create some test words
        for ($i = 0; $i < 3; $i++) {
            $this->createWord([
                'text' => "available_word_{$i}",
                'status' => 'published'
            ]);
        }

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/words/available/matching?language_id={$this->language->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Available words retrieved successfully.'
            ]);
    }

    /**
     * Helper method to initialize tenant context
     */
    protected function initializeTenantContext(Tenant $tenant): void
    {
        // The tenant is already initialized and seeded in createTestTenant
        // This method is kept for compatibility but not needed with enhanced trait
    }

    /**
     * Helper method to create tenant admin
     */
    protected function createTenantAdmin(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge(['email' => 'admin@test.com',
                'membership' => 'admin',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    /**
     * Helper method to create tenant team member
     */
    protected function createTenantTeam(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge(['email' => 'team@test.com',
                'membership' => 'team',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    /**
     * Helper method to create tenant student
     */
    protected function createTenantStudent(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge(['email' => 'student@test.com',
                'membership' => 'student',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }
}
