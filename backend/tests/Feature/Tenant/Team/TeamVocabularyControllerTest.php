<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class TeamVocabularyControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamMember;
    protected User $studentUser;
    protected Language $sourceLanguage;
    protected Language $targetLanguage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();

        // Create test users in tenant context
        $this->teamMember = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Team Member',
                'email' => 'team@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        });

        $this->studentUser = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Student User',
                'email' => 'student@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        });

        // Create test languages
        $this->sourceLanguage = $this->runInTenantContext($this->tenant, function () {
            return Language::create([
                'name' => 'English',
                'code' => 'en',
                'native_name' => 'English',
                'direction' => 'ltr',
                'status' => 'active',
            ]);
        });

        $this->targetLanguage = $this->runInTenantContext($this->tenant, function () {
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

    protected function createTestWord(array $attributes = []): Word
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Word::create(array_merge([
                'word' => 'hello',
                'definition' => 'a greeting',
                'pronunciation' => '/həˈloʊ/',
                'example_sentence' => 'Hello, how are you?',
                'language_id' => $this->sourceLanguage->id,
                'part_of_speech' => 'interjection',
                'difficulty_level' => 'beginner',
                'frequency_rank' => 1,
                'status' => 'active',
                'created_by' => $this->teamMember->id,
            ], $attributes));
        });
    }

    /**
     * Test team member can list vocabulary
     * 
     * @test
     */
    public function test_team_member_can_list_vocabulary()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $word1 = $this->createTestWord(['word' => 'hello']);
        $word2 = $this->createTestWord(['word' => 'goodbye']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/vocabulary");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.word', 'hello')
            ->assertJsonPath('data.1.word', 'goodbye')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'word',
                        'definition',
                        'pronunciation',
                        'example_sentence',
                        'language_id',
                        'part_of_speech',
                        'difficulty_level',
                        'frequency_rank',
                        'status',
                        'created_by',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can create vocabulary word
     * 
     * @test
     */
    public function test_team_member_can_create_vocabulary_word()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $wordData = [
            'word' => 'hola',
            'definition' => 'hello in Spanish',
            'pronunciation' => '/ˈola/',
            'example_sentence' => 'Hola, ¿cómo estás?',
            'language_id' => $this->targetLanguage->id,
            'part_of_speech' => 'interjection',
            'difficulty_level' => 'beginner',
            'frequency_rank' => 1,
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/vocabulary", $wordData);

        $response->assertStatus(201)
            ->assertJsonPath('data.word', 'hola')
            ->assertJsonPath('data.definition', 'hello in Spanish')
            ->assertJsonPath('data.language_id', $this->targetLanguage->id)
            ->assertJsonPath('data.part_of_speech', 'interjection')
            ->assertJsonPath('data.difficulty_level', 'beginner')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.created_by', $this->teamMember->id);

        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('words', [
                'word' => 'hola',
                'definition' => 'hello in Spanish',
                'language_id' => $this->targetLanguage->id,
            ]);
        });
    }

    /**
     * Test vocabulary creation validation
     * 
     * @test
     */
    public function test_vocabulary_creation_validation()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Missing required fields
        $response = $this->postJson("/api/{$this->tenant->slug}/team/vocabulary", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['word', 'language_id']);

        // Invalid language
        $response = $this->postJson("/api/{$this->tenant->slug}/team/vocabulary", [
            'word' => 'test',
            'language_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_id']);

        // Invalid part of speech
        $response = $this->postJson("/api/{$this->tenant->slug}/team/vocabulary", [
            'word' => 'test',
            'language_id' => $this->sourceLanguage->id,
            'part_of_speech' => 'invalid_pos',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['part_of_speech']);

        // Invalid difficulty level
        $response = $this->postJson("/api/{$this->tenant->slug}/team/vocabulary", [
            'word' => 'test',
            'language_id' => $this->sourceLanguage->id,
            'difficulty_level' => 'invalid_level',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['difficulty_level']);
    }

    /**
     * Test team member can show vocabulary word
     * 
     * @test
     */
    public function test_team_member_can_show_vocabulary_word()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $word = $this->createTestWord();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/vocabulary/{$word->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $word->id)
            ->assertJsonPath('data.word', $word->word)
            ->assertJsonPath('data.definition', $word->definition)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'word',
                    'definition',
                    'pronunciation',
                    'example_sentence',
                    'language_id',
                    'part_of_speech',
                    'difficulty_level',
                    'frequency_rank',
                    'status',
                    'translations',
                    'audio_url',
                    'created_by',
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    /**
     * Test team member can update vocabulary word
     * 
     * @test
     */
    public function test_team_member_can_update_vocabulary_word()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $word = $this->createTestWord();

        $updateData = [
            'definition' => 'Updated definition',
            'pronunciation' => '/ˈʌpdeɪtɪd/',
            'example_sentence' => 'Updated example sentence',
            'difficulty_level' => 'intermediate',
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/vocabulary/{$word->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.definition', 'Updated definition')
            ->assertJsonPath('data.pronunciation', '/ˈʌpdeɪtɪd/')
            ->assertJsonPath('data.example_sentence', 'Updated example sentence')
            ->assertJsonPath('data.difficulty_level', 'intermediate');

        $this->runInTenantContext($this->tenant, function () use ($word) {
            $this->assertDatabaseHas('words', [
                'id' => $word->id,
                'definition' => 'Updated definition',
                'pronunciation' => '/ˈʌpdeɪtɪd/',
                'example_sentence' => 'Updated example sentence',
                'difficulty_level' => 'intermediate',
            ]);
        });
    }

    /**
     * Test team member can delete vocabulary word
     * 
     * @test
     */
    public function test_team_member_can_delete_vocabulary_word()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $word = $this->createTestWord();

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/vocabulary/{$word->id}");

        $response->assertStatus(204);

        $this->runInTenantContext($this->tenant, function () use ($word) {
            $this->assertDatabaseMissing('words', [
                'id' => $word->id,
            ]);
        });
    }

    /**
     * Test filtering vocabulary by language
     * 
     * @test
     */
    public function test_filtering_vocabulary_by_language()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $englishWord = $this->createTestWord([
            'word' => 'hello',
            'language_id' => $this->sourceLanguage->id
        ]);

        $spanishWord = $this->createTestWord([
            'word' => 'hola',
            'language_id' => $this->targetLanguage->id
        ]);

        // Filter by English
        $response = $this->getJson("/api/{$this->tenant->slug}/team/vocabulary?language_id={$this->sourceLanguage->id}");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.word', 'hello');

        // Filter by Spanish
        $response = $this->getJson("/api/{$this->tenant->slug}/team/vocabulary?language_id={$this->targetLanguage->id}");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.word', 'hola');
    }

    /**
     * Test filtering vocabulary by difficulty level
     * 
     * @test
     */
    public function test_filtering_vocabulary_by_difficulty_level()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $beginnerWord = $this->createTestWord([
            'word' => 'hello',
            'difficulty_level' => 'beginner'
        ]);

        $advancedWord = $this->createTestWord([
            'word' => 'sophisticated',
            'difficulty_level' => 'advanced'
        ]);

        // Filter by beginner level
        $response = $this->getJson("/api/{$this->tenant->slug}/team/vocabulary?difficulty_level=beginner");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.word', 'hello');

        // Filter by advanced level
        $response = $this->getJson("/api/{$this->tenant->slug}/team/vocabulary?difficulty_level=advanced");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.word', 'sophisticated');
    }

    /**
     * Test searching vocabulary
     * 
     * @test
     */
    public function test_searching_vocabulary()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $word1 = $this->createTestWord(['word' => 'hello']);
        $word2 = $this->createTestWord(['word' => 'help']);
        $word3 = $this->createTestWord(['word' => 'goodbye']);

        // Search for words starting with "hel"
        $response = $this->getJson("/api/{$this->tenant->slug}/team/vocabulary?search=hel");
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Search for exact word
        $response = $this->getJson("/api/{$this->tenant->slug}/team/vocabulary?search=goodbye");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.word', 'goodbye');
    }

    /**
     * Test students cannot access team vocabulary endpoints
     * 
     * @test
     */
    public function test_students_cannot_access_team_vocabulary_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['tenant']);

        $word = $this->createTestWord();

        // Test various endpoints
        $response = $this->getJson("/api/{$this->tenant->slug}/team/vocabulary");
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/vocabulary", [
            'word' => 'test',
            'language_id' => $this->sourceLanguage->id,
        ]);
        $response->assertStatus(403);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/vocabulary/{$word->id}", [
            'definition' => 'Updated definition',
        ]);
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/vocabulary/{$word->id}");
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access is blocked
     * 
     * @test
     */
    public function test_unauthenticated_access_blocked()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/team/vocabulary");
        $response->assertStatus(401);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/vocabulary", [
            'word' => 'test',
            'language_id' => $this->sourceLanguage->id,
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test nonexistent vocabulary word returns 404
     * 
     * @test
     */
    public function test_nonexistent_vocabulary_word_returns_404()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/vocabulary/99999");
        $response->assertStatus(404);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/vocabulary/99999", [
            'definition' => 'Updated definition',
        ]);
        $response->assertStatus(404);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/vocabulary/99999");
        $response->assertStatus(404);
    }

    /**
     * Test duplicate words are handled properly
     * 
     * @test
     */
    public function test_duplicate_words_are_handled_properly()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Create first word
        $wordData = [
            'word' => 'hello',
            'language_id' => $this->sourceLanguage->id,
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/vocabulary", $wordData);
        $response->assertStatus(201);

        // Try to create duplicate word in same language
        $response = $this->postJson("/api/{$this->tenant->slug}/team/vocabulary", $wordData);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['word']);
    }

    /**
     * Test valid parts of speech are accepted
     * 
     * @test
     */
    public function test_valid_parts_of_speech_are_accepted()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $validPartsOfSpeech = ['noun', 'verb', 'adjective', 'adverb', 'pronoun', 'preposition', 'conjunction', 'interjection'];

        foreach ($validPartsOfSpeech as $pos) {
            $wordData = [
                'word' => "test_{$pos}",
                'language_id' => $this->sourceLanguage->id,
                'part_of_speech' => $pos,
            ];

            $response = $this->postJson("/api/{$this->tenant->slug}/team/vocabulary", $wordData);
            $response->assertStatus(201);
        }
    }
}
