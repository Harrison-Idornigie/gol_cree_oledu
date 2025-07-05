<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TenantTestCase;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\Word;
use App\Models\Tenants\WordTranslation;
use App\Models\Tenants\Sentence;
use App\Models\Tenants\SentenceTranslation;
use App\Services\Tenants\Media\AudioProcessingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;

class TeamAudioUploadControllerTest extends TenantTestCase
{


    protected Tenant $tenant;
    protected User $teamUser;
    protected User $studentUser;
    protected Language $language;
    protected Language $targetLanguage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();
        // $this->initializeTenantContext($this->tenant);

        // Create users
        $this->teamUser = $this->createTenantTeam();
        $this->studentUser = $this->createTenantStudent();

        // Create languages
        $this->language = $this->createLanguage();
        $this->targetLanguage = $this->createLanguage(['code' => 'en', 'name' => 'English']);

        // Mock storage
        Storage::fake('public');

        // Mock AudioProcessingService to avoid ffprobe dependency
        $this->mockAudioProcessingService();
    }

    protected function tearDown(): void
    {
        // Cleanup tenancy testing environment
        $this->tearDownTenancy();

        parent::tearDown();
    }

    protected function mockAudioProcessingService(): void
    {
        $mock = Mockery::mock(AudioProcessingService::class);

        // Mock processWordAudio method
        $mock->shouldReceive('processWordAudio')
            ->andReturn([
                'audio_url' => 'http://example.com/audio/test.mp3',
                'duration' => 2.5,
                'collection' => 'pronunciation',
                'media_id' => 1
            ]);

        // Mock processSentenceAudio method
        $mock->shouldReceive('processSentenceAudio')
            ->andReturn([
                'audio_url' => 'http://example.com/audio/sentence.mp3',
                'duration' => 3.0,
                'collection' => 'pronunciation',
                'media_id' => 2
            ]);

        // Mock processTranslationAudio method
        $mock->shouldReceive('processTranslationAudio')
            ->andReturn([
                'audio_url' => 'http://example.com/audio/translation.mp3',
                'duration' => 2.8,
                'collection' => 'pronunciation',
                'media_id' => 3
            ]);

        $this->app->instance(AudioProcessingService::class, $mock);
    }





    protected function createLanguage(array $attributes = []): Language
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Language::factory()->create(array_merge([
                'code' => 'crk',
                'name' => 'Plains Cree',
                'native_name' => 'nēhiyawēwin',
                'is_active' => true,
            ], $attributes));
        });
    }

    /** @test */
    public function team_member_can_upload_slow_audio_for_sentence()
    {
        $sentence = $this->createSentence();

        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        $file = UploadedFile::fake()->create('slow_audio.mp3', 150, 'audio/mpeg');

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}/audio-slow", [
            'audio' => $file,
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
                'message' => 'Slow audio uploaded successfully.'
            ]);
    }

    /** @test */
    public function team_member_can_upload_audio_for_sentence_translation()
    {
        $sentence = $this->createSentence();
        $translation = $this->createSentenceTranslation($sentence);

        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        $file = UploadedFile::fake()->create('translation_audio.mp3', 120, 'audio/mpeg');

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}/translations/{$translation->id}/audio", [
            'audio' => $file,
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
                'message' => 'Translation audio uploaded successfully.'
            ]);
    }

    /** @test */
    public function team_member_can_upload_audio_for_word_translation()
    {
        $word = $this->createWord();
        $translation = $this->createWordTranslation($word);

        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        $file = UploadedFile::fake()->create('word_translation_audio.mp3', 80, 'audio/mpeg');

        $response = $this->postJson("/api/{$this->tenant->slug}/team/words/{$word->id}/translations/{$translation->id}/audio", [
            'audio' => $file,
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
                'message' => 'Translation audio uploaded successfully.'
            ]);
    }

    /** @test */
    public function audio_upload_validates_file_type()
    {
        $sentence = $this->createSentence();

        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        // Invalid file type
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}/audio-slow", [
            'audio' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio']);
    }

    /** @test */
    public function audio_upload_validates_file_size()
    {
        $sentence = $this->createSentence();

        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        // File too large (over 20MB)
        $file = UploadedFile::fake()->create('large_audio.mp3', 25000, 'audio/mpeg');

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}/audio-slow", [
            'audio' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio']);
    }

    /** @test */
    public function audio_upload_requires_authentication()
    {
        $sentence = $this->createSentence();
        $file = UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg');

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}/audio-slow", [
            'audio' => $file,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function students_cannot_upload_audio()
    {
        $sentence = $this->createSentence();

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $file = UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg');

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}/audio-slow", [
            'audio' => $file,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function audio_upload_returns_404_for_nonexistent_sentence()
    {
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        $file = UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg');

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/99999/audio-slow", [
            'audio' => $file,
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function audio_upload_returns_404_for_nonexistent_translation()
    {
        $sentence = $this->createSentence();

        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        $file = UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg');

        $response = $this->postJson("/api/{$this->tenant->slug}/team/sentences/{$sentence->id}/translations/99999/audio", [
            'audio' => $file,
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function audio_upload_is_tenant_isolated()
    {
        // Create sentence in current tenant
        $sentence = $this->createSentence();

        // Create another tenant
        $otherTenant = $this->createTestTenant('other-tenant');

        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');

        $file = UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg');

        // Try to upload audio using other tenant's slug
        $response = $this->postJson("/api/{$otherTenant->slug}/team/sentences/{$sentence->id}/audio-slow", [
            'audio' => $file,
        ]);

        $response->assertStatus(404);
    }

    protected function createSentence(array $attributes = []): Sentence
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Sentence::factory()->create(array_merge([
                'language_id' => $this->language->id,
            ], $attributes));
        });
    }

    protected function createWord(array $attributes = []): Word
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Word::factory()->create(array_merge([
                'language_id' => $this->language->id,
            ], $attributes));
        });
    }

    protected function createSentenceTranslation(Sentence $sentence): SentenceTranslation
    {
        return $this->runInTenantContext($this->tenant, function () use ($sentence) {
            return SentenceTranslation::factory()->create([
                'sentence_id' => $sentence->id,
                'language_id' => $this->targetLanguage->id,
            ]);
        });
    }

    protected function createWordTranslation(Word $word): WordTranslation
    {
        return $this->runInTenantContext($this->tenant, function () use ($word) {
            return WordTranslation::factory()->create([
                'word_id' => $word->id,
                'language_id' => $this->targetLanguage->id,
            ]);
        });
    }
}
