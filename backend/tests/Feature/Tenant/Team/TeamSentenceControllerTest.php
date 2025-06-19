<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\Sentence;
use App\Models\Tenants\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class TeamSentenceControllerTest extends TestCase
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
    protected function createLanguage()
    {
        return $this->runInTenantContext($this->tenant, function () {
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
     * Helper to create a test sentence
     */
    protected function createSentence(array $attributes = [])
    {
        $defaultAttrs = [
            'text' => 'This is a test sentence ' . rand(1000, 9999),
            'language_id' => $this->language->id,
            'status' => 'published',
            'created_by' => $this->teamUser->id,
        ];
        
        return $this->runInTenantContext($this->tenant, function () use ($defaultAttrs, $attributes) {
            return Sentence::create(array_merge($defaultAttrs, $attributes));
        });
    }
    
    /**
     * Test listing sentences (index method)
     */
    public function test_index_success()
    {
        // Create some test sentences
        $sentences = [];
        for ($i = 0; $i < 3; $i++) {
            $sentences[] = $this->createSentence();
        }
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/sentences");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Sentences retrieved successfully.'
            ]);
    }

    /**
     * Test creating a new sentence (store method)
     */
    public function test_store_success()
    {
        // Create some words that can be used in the sentence
        $word1 = $this->createWord();
        $word2 = $this->createWord();
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $sentenceData = [
            'text' => 'New test sentence',
            'language_id' => $this->language->id,
            'status' => 'draft',
            'word_mappings' => [
                ['word_id' => $word1->id, 'position' => 0],
                ['word_id' => $word2->id, 'position' => 2]
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences", $sentenceData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'text',
                    'language_id',
                    'status',
                    'created_by',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Sentence created successfully.',
                'data' => [
                    'text' => 'New test sentence',
                    'language_id' => $this->language->id,
                    'status' => 'draft'
                ]
            ]);
    }

    /**
     * Test validation failure when creating a sentence
     */
    public function test_store_validation_failure()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        // Missing required fields
        $sentenceData = [
            'text' => '' // Empty text, which should fail validation
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences", $sentenceData);

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
     * Test retrieving a specific sentence (show method)
     */
    public function test_show_success()
    {
        // Create test sentence
        $sentence = $this->createSentence();
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'text',
                    'language_id',
                    'status',
                    'created_by',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Sentence retrieved successfully.',
                'data' => [
                    'id' => $sentence->id,
                    'text' => $sentence->text
                ]
            ]);
    }

    /**
     * Test updating a sentence (update method)
     */
    public function test_update_success()
    {
        // Create test sentence
        $sentence = $this->createSentence();
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $updateData = [
            'text' => 'Updated test sentence',
            'status' => 'review'
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Sentence updated successfully.',
                'data' => [
                    'id' => $sentence->id,
                    'text' => 'Updated test sentence',
                    'status' => 'review'
                ]
            ]);
    }

    /**
     * Test deleting a sentence (destroy method)
     */
    public function test_destroy_success()
    {
        // Create test sentence
        $sentence = $this->createSentence();
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Sentence deleted successfully.'
            ]);
        
        // Verify the sentence was actually deleted
        $this->runInTenantContext($this->tenant, function () use ($sentence) {
            $this->assertDatabaseMissing('sentences', ['id' => $sentence->id]);
        });
    }

    /**
     * Test getting available words for sentence creation
     */
    public function test_get_available_words_success()
    {
        // Create some test words
        for ($i = 0; $i < 3; $i++) {
            $this->createWord([
                'status' => 'published'
            ]);
        }
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/sentences/available-words?language_id={$this->language->id}");

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
     * Test validating words for a sentence
     */
    public function test_validate_sentence_words_success()
    {
        // Create some test words
        $word1 = $this->createWord();
        $word2 = $this->createWord();
        $word3 = $this->createWord();
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $validationData = [
            'text' => 'This is a test sentence',
            'language_id' => $this->language->id,
            'word_ids' => [$word1->id, $word2->id, $word3->id]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/validate-words", $validationData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Words validated successfully.'
            ]);
    }

    /**
     * Test AI-assisted text analysis
     */
    public function test_analyze_sentence_text_success()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $analysisData = [
            'text' => 'This is a test sentence for analysis',
            'language_id' => $this->language->id
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/analyze-text", $analysisData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Sentence text analyzed successfully.'
            ]);
    }

    /**
     * Test creating a sentence with automatic word mapping
     */
    public function test_create_with_auto_mapping_success()
    {
        // Create some words
        $word1 = $this->createWord(['text' => 'test']);
        $word2 = $this->createWord(['text' => 'sentence']);
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $mappingData = [
            'text' => 'This is a test sentence',
            'language_id' => $this->language->id,
            'status' => 'draft'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/create-with-mapping", $mappingData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'text',
                    'language_id',
                    'status',
                    'created_by',
                    'word_mappings'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Sentence created with automatic mapping successfully.'
            ]);
    }

    /**
     * Test adding a translation to a sentence
     */
    public function test_add_translation_success()
    {
        // Create test sentence
        $sentence = $this->createSentence();
        
        // Create a second language for translation
        $targetLanguage = $this->runInTenantContext($this->tenant, function () {
            return Language::create([
                'name' => 'Target Language',
                'code' => 'tg',
                'native_name' => 'Target Language Native',
                'is_active' => true
            ]);
        });
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $translationData = [
            'text' => 'This is a translated sentence',
            'language_id' => $targetLanguage->id
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}/translations", $translationData);

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
     * Test uploading audio for a sentence
     */
    public function test_upload_audio_success()
    {
        // Create test sentence
        $sentence = $this->createSentence();
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);
        
        // Mock the Storage facade
        Storage::fake('public');
        
        $file = UploadedFile::fake()->create('audio.mp3', 100);
        
        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}/audio", [
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
     * Test updating word timings in a sentence
     */
    public function test_update_word_timings_success()
    {
        // Create test sentence
        $sentence = $this->createSentence();
        
        // Create words for the sentence
        $word1 = $this->createWord();
        $word2 = $this->createWord();
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $timingsData = [
            'word_timings' => [
                [
                    'word_id' => $word1->id,
                    'start_time' => 0.5,
                    'end_time' => 1.2
                ],
                [
                    'word_id' => $word2->id,
                    'start_time' => 1.3,
                    'end_time' => 2.1
                ]
            ]
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}/word-timings", $timingsData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Word timings updated successfully.'
            ]);
    }

    /**
     * Test reordering words in a sentence
     */
    public function test_reorder_words_success()
    {
        // Create test sentence
        $sentence = $this->createSentence();
        
        // Create words for the sentence
        $word1 = $this->createWord();
        $word2 = $this->createWord();
        $word3 = $this->createWord();
        
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, ['*']);

        $reorderData = [
            'word_order' => [
                ['word_id' => $word2->id, 'position' => 0],
                ['word_id' => $word1->id, 'position' => 1],
                ['word_id' => $word3->id, 'position' => 2]
            ]
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}/words/reorder", $reorderData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Words reordered successfully.'
            ]);
    }

    /**
     * Test student user cannot access team endpoints
     */
    public function test_student_cannot_access()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/sentences");

        $response->assertStatus(403);
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
