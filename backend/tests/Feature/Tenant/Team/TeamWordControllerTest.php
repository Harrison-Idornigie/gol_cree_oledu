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

class TeamWordControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamUser;
    protected User $adminUser;
    protected User $studentUser;
    protected Language $language;
    protected array $testWords;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenant and immediately initialize context
        $this->tenant = $this->createTestTenant();
        tenancy()->initialize($this->tenant);

        // Create users with different roles in tenant context
        $this->teamUser = $this->createTenantTeam();
        $this->adminUser = $this->createTenantAdmin();
        $this->studentUser = $this->createTenantStudent();

        // Create test language
        $this->language = $this->createLanguage();

        // Create some test words for index tests
        $this->testWords = [];
        for ($i = 0; $i < 3; $i++) {
            $this->testWords[] = $this->createWord();
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }





    /**
     * Test listing words (index method)
     */
    public function test_index_success()
    {
        // Use pre-created test words
        $this->assertCount(3, $this->testWords);

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

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
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

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
                    'pronunciation_key',
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
                    'part_of_speech' => 'verb'
                ]
            ]);
    }

    /**
     * Test validation failure when creating a word
     */
    public function test_store_validation_failure()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        // Missing required fields
        $wordData = [
            'text' => '' // Empty text, which should fail validation
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/words", $wordData);

        $response->assertStatus(422)
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

        // Debug: Check if word exists and has correct tenant_id
        $this->assertNotNull($word);
        $this->assertNotNull($word->id);
        $this->assertEquals($this->tenant->id, $word->tenant_id);

        // Debug: Check if we can find the word in the current tenant context
        tenancy()->initialize($this->tenant);
        $foundWord = \App\Models\Tenants\Word::find($word->id);
        $this->assertNotNull($foundWord, "Word should be findable in tenant context");

        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

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
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

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
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

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
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        $wordData = [
            'operation' => 'create',
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
                    'success' => [
                        '*' => [
                            'index',
                            'word' => [
                                'id',
                                'text',
                                'language_id'
                            ]
                        ]
                    ],
                    'errors',
                    'summary' => [
                        'total',
                        'successful',
                        'failed'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Bulk operation completed.'
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
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        $updateData = [
            'operation' => 'update',
            'words' => [
                [
                    'id' => $word1->id,
                    'text' => 'bulk_updated_1',
                    'part_of_speech' => 'verb',
                    'language_id' => $word1->language_id
                ],
                [
                    'id' => $word2->id,
                    'text' => 'bulk_updated_2',
                    'part_of_speech' => 'adverb',
                    'language_id' => $word2->language_id
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
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        $deleteData = [
            'operation' => 'delete',
            'words' => [$word1->id, $word2->id]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/words/bulk-delete", $deleteData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'success',
                    'errors',
                    'summary' => [
                        'total',
                        'successful',
                        'failed'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Bulk delete completed.',
                'data' => [
                    'summary' => [
                        'total' => 2,
                        'successful' => 2,
                        'failed' => 0
                    ]
                ]
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
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

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
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

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
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

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
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

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
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

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
        // Initialize the tenant context for the test
        tenancy()->initialize($tenant);
    }

    /**
     * Helper method to create tenant admin
     */
    protected function createTenantAdmin(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge([
                'email' => 'admin@test.com',
                'membership' => 'admin',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }
}
