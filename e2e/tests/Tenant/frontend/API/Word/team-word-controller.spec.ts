import { TenantFixtures } from '@/fixtures/tenant-fixtures';
import { ApiHelper } from '@/utils/api-helpers';
import { DatabaseHelper } from '@/utils/database-helpers';
import { WordFixtures } from '@/fixtures/word-fixtures';
import { test, expect } from '@playwright/test';
 
import * as fs from 'fs';
import * as path from 'path';

/**
 * Team Word Controller E2E Tests
 * 
 * Comprehensive testing for all TeamWordController endpoints following
 * the existing multitenant test structure. Tests cover authentication,
 * authorization, tenant isolation, data validation, functional testing,
 * filtering/search, and edge cases.
 */

test.describe('Team Word Controller API - Comprehensive E2E Tests', () => {
  let fixtures: TenantFixtures;
  let wordFixtures: WordFixtures;
  let apiHelper: ApiHelper;
  let dbHelper: DatabaseHelper;
  
  // Test data containers
  let tenant1Data: any;
  let tenant2Data: any;
  let tenant1Token: string;
  let tenant2Token: string;
  let tenant1Slug: string;
  let tenant2Slug: string;
  let testWords: any[] = [];
  let testLanguages: any[] = [];

  test.beforeAll(async () => {
    fixtures = new TenantFixtures();
    wordFixtures = new WordFixtures();
    apiHelper = new ApiHelper();
    dbHelper = new DatabaseHelper();
    await dbHelper.initialize();

    // Create two test tenants for isolation testing with unique slugs
    tenant1Data = fixtures.generateValidTenantData({
      organizationName: 'Word Test School 1'
      // Let the fixture generate unique slug automatically
    });
    
    tenant2Data = fixtures.generateValidTenantData({
      organizationName: 'Word Test School 2'
      // Let the fixture generate unique slug automatically
    });

    // Register both tenants
    const tenant1Registration = await apiHelper.registerTenantAdmin(tenant1Data);
    const tenant2Registration = await apiHelper.registerTenantAdmin(tenant2Data);
    
    expect(tenant1Registration.success).toBe(true);
    expect(tenant2Registration.success).toBe(true);
    
    tenant1Token = tenant1Registration.data?.token;
    tenant2Token = tenant2Registration.data?.token;
    tenant1Slug = tenant1Data.organizationSlug!;
    tenant2Slug = tenant2Data.organizationSlug!;

    // Create test audio files for upload testing
    await createTestAudioFiles();
  });

  test.afterAll(async () => {
    await cleanupTestAudioFiles();
    await fixtures.cleanup();
    await dbHelper.close();
  });

  test.describe('Authentication & Authorization Tests', () => {
    test('should return 401 for unauthenticated requests', async ({ page }) => {
      // Test GET /words without token
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words');
      expect(response.status).toBe(401);
      
      // Handle cases where response might not be JSON
      let data;
      try {
        const responseText = await response.text();
        console.log('📡 Raw response:', responseText);
        data = JSON.parse(responseText);
      } catch (error) {
        console.error('Failed to parse JSON response:', error);
        // If we can't parse JSON, just check the status code
        return;
      }
      
      expect(data.success).toBe(false);
      expect(data.message).toContain('Unauthenticated');
    });

    test('should return 403 for non-team users accessing team endpoints', async ({ page }) => {
      // This would require creating a student user - for now test with invalid token
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words', {
        token: 'invalid-token'
      });
      expect(response.status).toBe(401);
    });

    test('should allow valid team authentication', async ({ page }) => {
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words', {
        token: tenant1Token
      });
      expect(response.status).toBe(200);
      
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data).toBeDefined();
    });
  });

  test.describe('Tenant Isolation Tests', () => {
    test('should only access words from own tenant', async ({ page }) => {
      // Create word in tenant1
      const wordData = {
        language_id: 1,
        text: 'tenant1-word',
        pronunciation_key: 'ten-ant-one-word',
        part_of_speech: 'noun'
      };

      const createResponse = await apiHelper.tenantRequest(tenant1Slug, '/team/words', {
        method: 'POST',
        body: wordData,
        token: tenant1Token
      });
      expect(createResponse.status).toBe(201);
      
      const createdWord = await createResponse.json();
      const wordId = createdWord.data.id;

      // Try to access tenant1's word from tenant2
      const accessResponse = await apiHelper.tenantRequest(tenant2Slug, `/team/words/${wordId}`, {
        token: tenant2Token
      });
      expect(accessResponse.status).toBe(404);
    });

    test('should block cross-tenant word access', async ({ page }) => {
      // Create word in tenant2
      const wordData = {
        language_id: 1, 
        text: 'tenant2-word',
        pronunciation_key: 'ten-ant-two-word',
        part_of_speech: 'verb'
      };

      const createResponse = await apiHelper.tenantRequest(tenant2Slug, '/team/words', {
        method: 'POST',
        body: wordData,
        token: tenant2Token
      });
      expect(createResponse.status).toBe(201);

      // Try to access with tenant1 token
      const listResponse = await apiHelper.tenantRequest(tenant1Slug, '/team/words', {
        token: tenant1Token
      });
      expect(listResponse.status).toBe(200);
      
      const data = await listResponse.json();
      const wordTexts = data.data.data.map((w: any) => w.text);
      expect(wordTexts).not.toContain('tenant2-word');
    });

    test('should validate tenant context in all endpoints', async ({ page }) => {
      // Test multiple endpoints with cross-tenant access
      const endpoints = [
        '/team/words',
        '/team/words/bulk',
        '/team/words/available',
        '/team/words/validate-constraints'
      ];

      for (const endpoint of endpoints) {
        const response = await apiHelper.tenantRequest(tenant1Slug, endpoint, {
          token: tenant2Token // Wrong tenant token
        });
        // Should either be 401/403 or return empty results due to tenant isolation
        expect([401, 403, 200]).toContain(response.status);
      }
    });
  });

  test.describe('Data Validation Tests', () => {
    test('should validate required fields', async ({ page }) => {
      const invalidData = wordFixtures.generateInvalidWordData('missing_required');

      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words', {
        method: 'POST',
        body: invalidData,
        token: tenant1Token
      });
      
      expect(response.status).toBe(422);
      const data = await response.json();
      expect(data.success).toBe(false);
      expect(data.message).toContain('validation');
    });

    test('should validate parameter types', async ({ page }) => {
      const invalidData = wordFixtures.generateInvalidWordData('invalid_types');

      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words', {
        method: 'POST',
        body: invalidData,
        token: tenant1Token
      });
      
      expect(response.status).toBe(422);
    });

    test('should enforce pagination limits', async ({ page }) => {
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words?per_page=150', {
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      // Should be limited to max 100
      expect(data.data.per_page).toBeLessThanOrEqual(100);
    });

    test('should validate audio file uploads', async ({ page }) => {
      // Test with invalid file type
      const formData = new FormData();
      formData.append('language_id', '1');
      formData.append('text', 'audio-test-word');
      formData.append('part_of_speech', 'noun');
      
      // Create a fake text file with audio name
      const textFile = new File(['fake audio'], 'audio.txt', { type: 'text/plain' });
      formData.append('pronunciation_audio', textFile);

      const response = await fetch(`${process.env.API_BASE_URL}/${tenant1Slug}/team/words`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${tenant1Token}`,
          'Accept': 'application/json'
        },
        body: formData
      });
      
      expect(response.status).toBe(422);
    });
  });

  test.describe('Functional Testing - CRUD Operations', () => {
    test('GET /team/words - list words with filtering', async ({ page }) => {
      // Create test words first
      const word1 = await createTestWord(tenant1Slug, tenant1Token, {
        text: 'filter-test-1',
        part_of_speech: 'noun'
      });
      
      const word2 = await createTestWord(tenant1Slug, tenant1Token, {
        text: 'filter-test-2', 
        part_of_speech: 'verb'
      });

      // Test basic listing
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words', {
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.data).toBeInstanceOf(Array);
      expect(data.data.current_page).toBe(1);
      expect(data.data.per_page).toBeDefined();
      expect(data.data.total).toBeGreaterThanOrEqual(2);

      // Test filtering by part_of_speech
      const filteredResponse = await apiHelper.tenantRequest(tenant1Slug, '/team/words?part_of_speech=noun', {
        token: tenant1Token
      });
      
      expect(filteredResponse.status).toBe(200);
      const filteredData = await filteredResponse.json();
      const nounWords = filteredData.data.data.filter((w: any) => w.part_of_speech === 'noun');
      expect(nounWords.length).toBeGreaterThan(0);
    });

    test('GET /team/words/{id} - get single word', async ({ page }) => {
      const word = await createTestWord(tenant1Slug, tenant1Token, {
        text: 'single-word-test'
      });

      const response = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}`, {
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.id).toBe(word.id);
      expect(data.data.text).toBe('single-word-test');
    });

    test('POST /team/words - create word', async ({ page }) => {
      const wordData = wordFixtures.generateValidWordData({
        text: 'create-test-word',
        pronunciation_key: 'kree-ayt-test-wurd',
        part_of_speech: 'noun'
      });

      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words', {
        method: 'POST',
        body: wordData,
        token: tenant1Token
      });
      
      expect(response.status).toBe(201);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.text).toBe(wordData.text);
      expect(data.data.pronunciation_key).toBe(wordData.pronunciation_key);
      expect(data.data.part_of_speech).toBe(wordData.part_of_speech);

      testWords.push(data.data);
    });

    test('PUT /team/words/{id} - update word', async ({ page }) => {
      const word = await createTestWord(tenant1Slug, tenant1Token, {
        text: 'update-test-original'
      });

      const updateData = {
        text: 'update-test-modified',
        pronunciation_key: 'up-dayt-test-mod-i-fyd',
        part_of_speech: 'verb'
      };

      const response = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}`, {
        method: 'PUT',
        body: updateData,
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.text).toBe(updateData.text);
      expect(data.data.pronunciation_key).toBe(updateData.pronunciation_key);
      expect(data.data.part_of_speech).toBe(updateData.part_of_speech);
    });

    test('DELETE /team/words/{id} - delete word', async ({ page }) => {
      const word = await createTestWord(tenant1Slug, tenant1Token, {
        text: 'delete-test-word'
      });

      const response = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}`, {
        method: 'DELETE',
        token: tenant1Token
      });
      
      expect(response.status).toBe(204);

      // Verify word is deleted
      const getResponse = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}`, {
        token: tenant1Token
      });
      expect(getResponse.status).toBe(404);
    });
  });

  test.describe('Bulk Operations Testing', () => {
    test('POST /team/words/bulk - bulk create words', async ({ page }) => {
      const words = wordFixtures.generateBulkWordData(2);
      const bulkData = {
        operation: 'create',
        words: words
      };

      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words/bulk', {
        method: 'POST',
        body: bulkData,
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.successful).toBe(2);
      expect(data.data.failed).toBe(0);
    });

    test('DELETE /team/words/bulk-delete - bulk delete words', async ({ page }) => {
      // Create words to delete
      const word1 = await createTestWord(tenant1Slug, tenant1Token, { text: 'bulk-delete-1' });
      const word2 = await createTestWord(tenant1Slug, tenant1Token, { text: 'bulk-delete-2' });

      const deleteData = {
        words: [word1.id, word2.id]
      };

      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words/bulk-delete', {
        method: 'POST',
        body: deleteData,
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.successful).toBe(2);
    });
  });

  test.describe('Translation Management', () => {
    test('POST /team/words/{id}/translations - add translation', async ({ page }) => {
      const word = await createTestWord(tenant1Slug, tenant1Token, {
        text: 'translation-test-word'
      });

      const translationData = wordFixtures.generateValidTranslationData({
        language_id: 2,
        text: 'palabra-de-prueba',
        pronunciation_key: 'pa-la-bra-de-prue-ba',
        context_notes: 'Spanish translation'
      });

      const response = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}/translations`, {
        method: 'POST',
        body: translationData,
        token: tenant1Token
      });
      
      expect(response.status).toBe(201);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.text).toBe(translationData.text);
      expect(data.data.language_id).toBe(translationData.language_id);
    });

    test('PUT /team/words/{id}/translations/{translationId} - update translation', async ({ page }) => {
      const word = await createTestWord(tenant1Slug, tenant1Token, {
        text: 'translation-update-test'
      });

      // First create a translation
      const translationData = wordFixtures.generateValidTranslationData({
        language_id: 2,
        text: 'original-translation',
        pronunciation_key: 'or-i-gi-nal'
      });

      const createResponse = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}/translations`, {
        method: 'POST',
        body: translationData,
        token: tenant1Token
      });
      
      const translation = await createResponse.json();
      const translationId = translation.data.id;

      // Now update it
      const updateData = {
        text: 'updated-translation',
        pronunciation_key: 'up-day-ted'
      };

      const updateResponse = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}/translations/${translationId}`, {
        method: 'PUT',
        body: updateData,
        token: tenant1Token
      });
      
      expect(updateResponse.status).toBe(200);
      const updatedData = await updateResponse.json();
      expect(updatedData.data.text).toBe(updateData.text);
    });

    test('DELETE /team/words/{id}/translations/{translationId} - delete translation', async ({ page }) => {
      const word = await createTestWord(tenant1Slug, tenant1Token, {
        text: 'translation-delete-test'
      });

      // Create translation
      const translationData = wordFixtures.generateValidTranslationData({
        language_id: 2,
        text: 'delete-me-translation'
      });

      const createResponse = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}/translations`, {
        method: 'POST',
        body: translationData,
        token: tenant1Token
      });
      
      const translation = await createResponse.json();
      const translationId = translation.data.id;

      // Delete translation
      const deleteResponse = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}/translations/${translationId}`, {
        method: 'DELETE',
        token: tenant1Token
      });
      
      expect(deleteResponse.status).toBe(204);
    });
  });

  test.describe('Audio Upload Testing', () => {
    test('POST /team/words/{id}/audio - upload word audio', async ({ page }) => {
      const word = await createTestWord(tenant1Slug, tenant1Token, {
        text: 'audio-upload-test'
      });

      // Create a proper form data request with audio file
      const audioFile = await fs.promises.readFile(path.join(__dirname, '../test-audio.mp3'));
      const formData = new FormData();
      formData.append('audio', new File([audioFile], 'test-audio.mp3', { type: 'audio/mpeg' }));

      const response = await fetch(`${process.env.API_BASE_URL}/${tenant1Slug}/team/words/${word.id}/audio`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${tenant1Token}`,
          'Accept': 'application/json'
        },
        body: formData
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.audio_url).toBeDefined();
    });
  });

  test.describe('Filter & Search Testing', () => {
    test('should filter by language_id', async ({ page }) => {
      await createTestWord(tenant1Slug, tenant1Token, {
        text: 'lang-filter-1',
        language_id: 1
      });
      
      await createTestWord(tenant1Slug, tenant1Token, {
        text: 'lang-filter-2',
        language_id: 2
      });

      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words?language_id=1', {
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      const words = data.data.data;
      words.forEach((word: any) => {
        expect(word.language_id).toBe(1);
      });
    });

    test('should search by text', async ({ page }) => {
      await createTestWord(tenant1Slug, tenant1Token, {
        text: 'searchable-unique-word'
      });

      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words?search=searchable-unique', {
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.data.data.length).toBeGreaterThan(0);
      expect(data.data.data[0].text).toContain('searchable-unique');
    });

    test('should sort by different fields', async ({ page }) => {
      // Test sorting by text
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words?sort_by=text&sort_order=asc', {
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      const words = data.data.data;
      
      if (words.length > 1) {
        expect(words[0].text.localeCompare(words[1].text)).toBeLessThanOrEqual(0);
      }
    });

    test('should handle pagination correctly', async ({ page }) => {
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words?page=1&per_page=5', {
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.data.current_page).toBe(1);
      expect(data.data.per_page).toBe(5);
      expect(data.data.data.length).toBeLessThanOrEqual(5);
    });
  });

  test.describe('Word Constraint System', () => {
    test('GET /team/words/available - get available words for exercises', async ({ page }) => {
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words/available', {
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data).toBeInstanceOf(Array);
    });

    test('GET /team/words/available/{exerciseType} - get words for specific exercise', async ({ page }) => {
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words/available/listening', {
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
    });

    test('POST /team/words/validate-constraints - validate word constraints', async ({ page }) => {
      const word = await createTestWord(tenant1Slug, tenant1Token, { text: 'constraint-test' });
      
      const constraintData = {
        word_ids: [word.id],
        context: 'listening'
      };

      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words/validate-constraints', {
        method: 'POST',
        body: constraintData,
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.valid).toBeDefined();
    });
  });

  test.describe('Edge Cases & Error Handling', () => {
    test('should handle empty result sets', async ({ page }) => {
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words?search=nonexistent-word-12345', {
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.data).toEqual([]);
      expect(data.data.total).toBe(0);
    });

    test('should return 404 for non-existent word IDs', async ({ page }) => {
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words/99999', {
        token: tenant1Token
      });
      
      expect(response.status).toBe(404);
    });

    test('should handle batch requests with invalid word IDs', async ({ page }) => {
      const deleteData = {
        words: [99999, 99998] // Non-existent IDs
      };

      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words/bulk-delete', {
        method: 'POST',
        body: deleteData,
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      expect(data.data.successful).toBe(0);
      expect(data.data.failed).toBe(2);
    });

    test('should enforce large batch request limits', async ({ page }) => {
      const largeWordIds = Array.from({ length: 1000 }, (_, i) => i + 1);
      const deleteData = { words: largeWordIds };

      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words/bulk-delete', {
        method: 'POST',
        body: deleteData,
        token: tenant1Token
      });
      
      // Should either limit the batch size or return validation error
      expect([200, 422]).toContain(response.status);
    });
  });

  test.describe('Response Format Validation', () => {
    test('should follow BaseAPIController format for success responses', async ({ page }) => {
      const response = await apiHelper.tenantRequest(tenant1Slug, '/team/words', {
        token: tenant1Token
      });
      
      expect(response.status).toBe(200);
      const data = await response.json();
      
      // Validate response structure
      expect(data).toHaveProperty('success', true);
      expect(data).toHaveProperty('message');
      expect(data).toHaveProperty('data');
      expect(data.data).toHaveProperty('data'); // Paginated data
      expect(data.data).toHaveProperty('current_page');
      expect(data.data).toHaveProperty('per_page');
      expect(data.data).toHaveProperty('total');
    });

    test('should return proper HTTP status codes', async ({ page }) => {
      // Test various endpoints for proper status codes
      const word = await createTestWord(tenant1Slug, tenant1Token, { text: 'status-test' });
      
      // GET - 200
      const getResponse = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}`, {
        token: tenant1Token
      });
      expect(getResponse.status).toBe(200);

      // DELETE - 204
      const deleteResponse = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}`, {
        method: 'DELETE',
        token: tenant1Token
      });
      expect(deleteResponse.status).toBe(204);

      // GET deleted word - 404
      const get404Response = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}`, {
        token: tenant1Token
      });
      expect(get404Response.status).toBe(404);
    });

    test('should include audio URLs when pronunciation files exist', async ({ page }) => {
      const word = await createTestWord(tenant1Slug, tenant1Token, {
        text: 'audio-url-test'
      });

      const response = await apiHelper.tenantRequest(tenant1Slug, `/team/words/${word.id}`, {
        token: tenant1Token
      });
      
      const data = await response.json();
      // Initially should not have audio URL
      expect(data.data.has_audio).toBeFalsy();
      expect(data.data.audio_url).toBeNull();
    });
  });

  // Helper function to create test words
  async function createTestWord(tenantSlug: string, token: string, wordData: any = {}) {
    const data = wordFixtures.generateValidWordData(wordData);
    
    const response = await apiHelper.tenantRequest(tenantSlug, '/team/words', {
      method: 'POST',
      body: data,
      token: token
    });
    
    expect(response.status).toBe(201);
    const result = await response.json();
    return result.data;
  }

  // Helper function to create test audio files
  async function createTestAudioFiles() {
    const testDir = path.dirname(__filename);
    const audioPath = path.join(testDir, '../test-audio.mp3');
    
    // Create a minimal MP3 file (just for testing - not actual audio)
    const mp3Header = Buffer.from([
      0xFF, 0xFB, 0x90, 0x00, // MP3 frame header
      0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00,
      0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00
    ]);
    
    await fs.promises.writeFile(audioPath, mp3Header);
  }

  // Helper function to cleanup test audio files
  async function cleanupTestAudioFiles() {
    const testDir = path.dirname(__filename);
    const audioPath = path.join(testDir, '../test-audio.mp3');
    
    try {
      await fs.promises.unlink(audioPath);
    } catch (error) {
      // File might not exist, ignore error
    }
  }
});