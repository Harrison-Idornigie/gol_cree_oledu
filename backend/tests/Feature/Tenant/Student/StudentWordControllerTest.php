<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\Word;
use App\Models\Tenants\WordTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class StudentWordControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $plainsCreeLanguage;
    protected Language $englishLanguage;
    protected array $testWords = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);

        // Create users with different roles in tenant context
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeam();

        // Create test environment
        $this->createTestLanguages();
        $this->createTestWords();
    }

    protected function createTestLanguages(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $this->plainsCreeLanguage = Language::create([
                'name' => 'Plains Cree',
                'code' => 'crk',
                'native_name' => 'nēhiyawēwin',
                'is_active' => true,
                'metadata' => [
                    'writing_system' => 'syllabics',
                    'has_audio' => true,
                    'cultural_context' => 'indigenous'
                ]
            ]);

            $this->englishLanguage = Language::create([
                'name' => 'English',
                'code' => 'en',
                'native_name' => 'English',
                'is_active' => true,
                'metadata' => [
                    'writing_system' => 'latin',
                    'has_audio' => true
                ]
            ]);
        });
    }

    protected function createTestWords(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            // Create Plains Cree words with syllabics
            $wordsData = [
                [
                    'text' => 'tanisi',
                    'syllabics' => 'ᑕᓂᓯ',
                    'part_of_speech' => 'interjection',
                    'proficiency_level' => 'A1',
                    'translations' => ['hello', 'how are you']
                ],
                [
                    'text' => 'atim',
                    'syllabics' => 'ᐊᑎᒼ',
                    'part_of_speech' => 'noun',
                    'proficiency_level' => 'A1',
                    'translations' => ['dog']
                ],
                [
                    'text' => 'nīpiy',
                    'syllabics' => 'ᓃᐱᕀ',
                    'part_of_speech' => 'noun',
                    'proficiency_level' => 'A2',
                    'translations' => ['water']
                ],
                [
                    'text' => 'kīsikāw',
                    'syllabics' => 'ᑮᓯᑳᐤ',
                    'part_of_speech' => 'noun',
                    'proficiency_level' => 'B1',
                    'translations' => ['day', 'sun']
                ]
            ];

            foreach ($wordsData as $wordData) {
                $word = Word::create([
                    'text' => $wordData['text'],
                    'language_id' => $this->plainsCreeLanguage->id,
                    'part_of_speech' => $wordData['part_of_speech'],
                    'status' => 'published',
                    'metadata' => [
                        'syllabics' => $wordData['syllabics'],
                        'proficiency_level' => $wordData['proficiency_level'],
                        'audio_url' => "audio/crk/{$wordData['text']}.mp3",
                        'cultural_notes' => 'Traditional Plains Cree word'
                    ],
                    'created_by' => $this->teamUser->id
                ]);

                // Create translations
                foreach ($wordData['translations'] as $translation) {
                    WordTranslation::create([
                        'word_id' => $word->id,
                        'language_id' => $this->englishLanguage->id,
                        'translation' => $translation,
                        'is_primary' => $translation === $wordData['translations'][0],
                        'created_by' => $this->teamUser->id
                    ]);
                }

                $this->testWords[] = $word;
            }
        });
    }

    /** @test */
    public function student_can_list_words_with_basic_filters()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get words
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'text',
                            'part_of_speech',
                            'language_id',
                            'metadata'
                        ]
                    ],
                    'current_page',
                    'total'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Words retrieved successfully.'
            ]);

        // Verify we get the expected number of words
        $this->assertCount(4, $response->json('data.data'));
    }

    /** @test */
    public function student_can_filter_words_by_language()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get Plains Cree words only
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?language_id={$this->plainsCreeLanguage->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'text',
                            'metadata' => [
                                'syllabics',
                                'proficiency_level',
                                'audio_url'
                            ]
                        ]
                    ]
                ]
            ]);

        // Verify all returned words are Plains Cree
        $words = $response->json('data.data');
        foreach ($words as $word) {
            $this->assertEquals($this->plainsCreeLanguage->id, $word['language_id']);
            $this->assertArrayHasKey('syllabics', $word['metadata']);
        }
    }

    /** @test */
    public function student_can_search_words_by_text()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to search for specific word
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?search=tanisi");

        $response->assertStatus(200);

        $words = $response->json('data.data');
        $this->assertGreaterThan(0, count($words));

        // Verify search results contain the searched term
        $found = false;
        foreach ($words as $word) {
            if (str_contains(strtolower($word['text']), 'tanisi')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search should return words containing the search term');
    }

    /** @test */
    public function student_can_filter_words_by_part_of_speech()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get nouns only
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?part_of_speech=noun");

        $response->assertStatus(200);

        $words = $response->json('data.data');
        foreach ($words as $word) {
            $this->assertEquals('noun', $word['part_of_speech']);
        }
    }

    /** @test */
    public function student_can_view_individual_word_with_syllabics()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $word = $this->testWords[0]; // 'tanisi'

        // API call to get specific word
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words/{$word->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'text',
                    'part_of_speech',
                    'metadata' => [
                        'syllabics',
                        'proficiency_level',
                        'audio_url',
                        'cultural_notes'
                    ],
                    'translations'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Word retrieved successfully.',
                'data' => [
                    'id' => $word->id,
                    'text' => 'tanisi',
                    'metadata' => [
                        'syllabics' => 'ᑕᓂᓯ',
                        'proficiency_level' => 'A1'
                    ]
                ]
            ]);
    }

    /** @test */
    public function student_can_get_word_translations()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $word = $this->testWords[0]; // 'tanisi'

        // API call to get word translations
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words/{$word->id}/translations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'translation',
                        'language_id',
                        'is_primary',
                        'audio_url'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Word translations retrieved successfully.'
            ]);

        // Verify translations are returned
        $translations = $response->json('data');
        $this->assertGreaterThan(0, count($translations));

        // Check for expected translations
        $translationTexts = array_column($translations, 'translation');
        $this->assertContains('hello', $translationTexts);
    }

    /** @test */
    public function student_can_get_word_translations_for_specific_language()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $word = $this->testWords[0]; // 'tanisi'

        // API call to get English translations only
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words/{$word->id}/translations?target_language_id={$this->englishLanguage->id}");

        $response->assertStatus(200);

        $translations = $response->json('data');
        foreach ($translations as $translation) {
            $this->assertEquals($this->englishLanguage->id, $translation['language_id']);
        }
    }

    /** @test */
    public function student_can_get_batch_words()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $wordIds = array_slice(array_column($this->testWords, 'id'), 0, 3);

        // API call to get multiple words
        $response = $this->postJson("/api/{$this->tenant->slug}/student/words/batch", [
            'word_ids' => $wordIds,
            'include_translations' => true,
            'include_audio' => true
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'text',
                        'metadata' => [
                            'syllabics',
                            'proficiency_level',
                            'audio_url'
                        ],
                        'translations'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Words retrieved successfully.'
            ]);

        // Verify we get the requested words
        $returnedWords = $response->json('data');
        $this->assertCount(3, $returnedWords);

        $returnedIds = array_column($returnedWords, 'id');
        foreach ($wordIds as $wordId) {
            $this->assertContains($wordId, $returnedIds);
        }
    }

    /** @test */
    public function student_cannot_access_words_from_other_tenants()
    {
        // Create another tenant with a word
        $otherTenant = $this->createTestTenant('other-tenant');
        $otherWord = null;

        $this->runInTenantContext($otherTenant, function () use (&$otherWord) {
            $language = Language::create([
                'name' => 'Test Language',
                'code' => 'test',
                'native_name' => 'Test',
                'is_active' => true
            ]);

            $otherWord = Word::create([
                'text' => 'other-word',
                'language_id' => $language->id,
                'part_of_speech' => 'noun',
                'status' => 'published',
                'created_by' => 1
            ]);
        });

        // Authenticate as student in original tenant
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Try to access word from other tenant
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words/{$otherWord->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Word not found.'
            ]);
    }

    /** @test */
    public function student_cannot_access_unpublished_words()
    {
        // Create an unpublished word
        $unpublishedWord = null;
        $this->runInTenantContext($this->tenant, function () use (&$unpublishedWord) {
            $unpublishedWord = Word::create([
                'text' => 'draft-word',
                'language_id' => $this->plainsCreeLanguage->id,
                'part_of_speech' => 'noun',
                'status' => 'draft',
                'created_by' => $this->teamUser->id
            ]);
        });

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Try to access unpublished word
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words/{$unpublishedWord->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthorized_user_cannot_access_words()
    {
        // Not authenticated
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words");
        $response->assertStatus(401);
    }

    /** @test */
    public function student_can_paginate_through_words()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call with pagination
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?per_page=2&page=1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data',
                    'current_page',
                    'per_page',
                    'total',
                    'last_page'
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['current_page']);
        $this->assertEquals(2, $data['per_page']);
        $this->assertLessThanOrEqual(2, count($data['data']));
    }

    /** @test */
    public function student_can_sort_words()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call with sorting
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?sort_by=text&sort_direction=asc");

        $response->assertStatus(200);

        $words = $response->json('data.data');
        $this->assertGreaterThan(0, count($words));

        // Verify sorting (first word should be alphabetically first)
        $texts = array_column($words, 'text');
        $sortedTexts = $texts;
        sort($sortedTexts);
        $this->assertEquals($sortedTexts, $texts);
    }

    /** @test */
    public function student_can_filter_words_by_proficiency_level()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get A1 level words only
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?proficiency_level=A1");

        $response->assertStatus(200);

        $words = $response->json('data.data');
        foreach ($words as $word) {
            $this->assertEquals('A1', $word['metadata']['proficiency_level']);
        }
    }

    /** @test */
    public function student_can_get_vocabulary_progression_constraints()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get words available for A2 level (should include A1 + A2)
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?max_proficiency_level=A2");

        $response->assertStatus(200);

        $words = $response->json('data.data');
        foreach ($words as $word) {
            $level = $word['metadata']['proficiency_level'];
            $this->assertContains($level, ['A1', 'A2'], 'A2 lessons should only access A1 and A2 vocabulary');
        }
    }

    /** @test */
    public function student_can_access_syllabics_content()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $word = $this->testWords[0]; // 'tanisi'

        // API call to get word with syllabics
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words/{$word->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'metadata' => [
                        'syllabics',
                        'audio_url',
                        'cultural_notes'
                    ]
                ]
            ]);

        $wordData = $response->json('data');
        $this->assertNotEmpty($wordData['metadata']['syllabics']);
        $this->assertEquals('ᑕᓂᓯ', $wordData['metadata']['syllabics']);
    }

    /** @test */
    public function student_can_get_audio_pronunciation_urls()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get words with audio URLs
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?include_audio=true");

        $response->assertStatus(200);

        $words = $response->json('data.data');
        foreach ($words as $word) {
            $this->assertArrayHasKey('audio_url', $word['metadata']);
            $this->assertStringContains('audio/crk/', $word['metadata']['audio_url']);
        }
    }

    /** @test */
    public function student_can_get_cultural_context_information()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $word = $this->testWords[0]; // 'tanisi'

        // API call to get word with cultural context
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words/{$word->id}");

        $response->assertStatus(200);

        $wordData = $response->json('data');
        $this->assertArrayHasKey('cultural_notes', $wordData['metadata']);
        $this->assertEquals('Traditional Plains Cree word', $wordData['metadata']['cultural_notes']);
    }
}
