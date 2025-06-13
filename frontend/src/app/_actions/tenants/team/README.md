# Team Word Management Server Actions

This directory contains server actions for team word management functionality in the application. These server actions serve as the bridge between the frontend components and the backend API endpoints.

## Overview

The word management system allows team members to:
- Create, read, update, and delete words
- Manage word translations in multiple languages
- Upload and manage audio files for pronunciation
- Perform bulk operations (import/export/delete)
- Validate word constraints for exercises

## Files

### `word-actions.ts`
Contains all server actions for word management operations.

## Available Server Actions

### Core Word Operations

#### `getWords(filters?: WordFilters)`
Fetches a paginated list of words with optional filtering and sorting.

**Parameters:**
- `filters` (optional): Object containing search, language, difficulty, and other filter options
- Supports pagination with `page` and `per_page` parameters
- Supports sorting with `sort_by` and `sort_order` parameters

**Returns:** `{ data: PaginatedWordResponse | null, error: string | null }`

#### `getWord(id: number)`
Fetches a single word with full details including translations.

**Parameters:**
- `id`: Word ID

**Returns:** `{ data: Word | null, error: string | null }`

#### `createWord(formData: FormData)`
Creates a new word with optional translations and audio.

**FormData fields:**
- `language_id`: Language ID for the word
- `text`: The word text
- `pronunciation_key`: Optional pronunciation guide
- `part_of_speech`: Optional part of speech
- `metadata`: Optional metadata object
- `pronunciation_audio`: Optional audio file
- `translations`: Array of translation objects

**Returns:** `{ data: Word | null, error: string | null }`

#### `updateWord(id: number, formData: FormData)`
Updates an existing word.

**Parameters:**
- `id`: Word ID
- `formData`: Same fields as createWord

**Returns:** `{ data: Word | null, error: string | null }`

#### `deleteWord(id: number)`
Deletes a single word.

**Parameters:**
- `id`: Word ID

**Returns:** `{ error: string | null }`

### Bulk Operations

#### `bulkDeleteWords(wordIds: number[])`
Deletes multiple words at once.

**Parameters:**
- `wordIds`: Array of word IDs to delete

**Returns:** `{ data: BulkOperationResult | null, error: string | null }`

#### `bulkImportWords(formData: FormData)`
Imports words from a CSV file or data.

**FormData fields:**
- `file`: CSV file with word data
- `operation`: Import operation type

**Returns:** `{ data: BulkOperationResult | null, error: string | null }`

#### `exportWords(filters?: Omit<WordFilters, 'page' | 'per_page'>)`
Exports words to CSV format.

**Parameters:**
- `filters` (optional): Filter options (excluding pagination)

**Returns:** `{ data: Blob | null, error: string | null }`

### Audio Management

#### `uploadWordAudio(wordId: number, audioFile: File)`
Uploads pronunciation audio for a word.

**Parameters:**
- `wordId`: Word ID
- `audioFile`: Audio file (MP3, WAV supported)

**Returns:** `{ data: AudioUploadResult | null, error: string | null }`

#### `uploadTranslationAudio(wordId: number, translationId: number, audioFile: File)`
Uploads pronunciation audio for a translation.

**Parameters:**
- `wordId`: Word ID
- `translationId`: Translation ID
- `audioFile`: Audio file

**Returns:** `{ data: AudioUploadResult | null, error: string | null }`

### Translation Management

#### `addTranslation(wordId: number, formData: FormData)`
Adds a new translation to an existing word.

**Parameters:**
- `wordId`: Word ID
- `formData`: Translation data including language_id, text, etc.

**Returns:** `{ data: WordTranslation | null, error: string | null }`

#### `updateTranslation(wordId: number, translationId: number, formData: FormData)`
Updates an existing translation.

**Parameters:**
- `wordId`: Word ID
- `translationId`: Translation ID
- `formData`: Updated translation data

**Returns:** `{ data: WordTranslation | null, error: string | null }`

#### `deleteTranslation(wordId: number, translationId: number)`
Deletes a translation.

**Parameters:**
- `wordId`: Word ID
- `translationId`: Translation ID

**Returns:** `{ error: string | null }`

### Exercise Integration

#### `getAvailableWords(options?: WordQueryOptions)`
Gets words available for exercise/lesson builders.

**Parameters:**
- `options` (optional): Query options including language filters, difficulty, tags, etc.

**Returns:** `{ data: Word[] | null, error: string | null }`

#### `validateWordConstraints(wordIds: number[], context?: string)`
Validates word constraints for exercises/lessons.

**Parameters:**
- `wordIds`: Array of word IDs to validate
- `context` (optional): Exercise context (listening, speaking, etc.)

**Returns:** `{ data: WordConstraintValidation | null, error: string | null }`

## Error Handling

All server actions follow a consistent error handling pattern:
- Catch and handle axios errors
- Extract meaningful error messages from API responses
- Return structured error responses
- Support validation error details

## Type Safety

All server actions are fully typed using TypeScript interfaces from `/types/tenant/word.ts`:
- `Word`: Main word interface
- `WordTranslation`: Translation interface
- `WordFilters`: Filter options
- `PaginatedWordResponse`: Paginated response structure
- `BulkOperationResult`: Bulk operation results
- `AudioUploadResult`: Audio upload results

## Authentication & Authorization

All server actions automatically handle:
- Authentication token injection for server-side requests
- Tenant context isolation
- Proper error handling for authentication failures

## Cache Revalidation

Server actions automatically revalidate Next.js cache paths after mutations:
- Word list pages: `/team/language/words`
- Individual word pages: `/team/language/words/{id}`

## Usage Examples

### Creating a Word

```typescript
import { createWord } from '@/app/_actions/tenants/team/word-actions';

const formData = new FormData();
formData.append('language_id', '1');
formData.append('text', 'hello');
formData.append('pronunciation_key', '/həˈloʊ/');
formData.append('part_of_speech', 'interjection');

// Add translations
formData.append('translations[0][language_id]', '2');
formData.append('translations[0][text]', 'hola');

const result = await createWord(formData);
if (result.error) {
  console.error('Error:', result.error);
} else {
  console.log('Created word:', result.data);
}
```

### Filtering Words

```typescript
import { getWords } from '@/app/_actions/tenants/team/word-actions';

const result = await getWords({
  search: 'hello',
  language_id: 1,
  difficulty: 'beginner',
  has_audio: true,
  sort_by: 'text',
  sort_order: 'asc',
  per_page: 20,
  page: 1
});
```

### Bulk Import

```typescript
import { bulkImportWords } from '@/app/_actions/tenants/team/word-actions';

const formData = new FormData();
formData.append('file', csvFile);
formData.append('operation', 'create');

const result = await bulkImportWords(formData);
console.log(`Imported ${result.data?.created} words`);
```

## API Endpoints

These server actions connect to the following backend API endpoints:

- `GET /api/{tenant}/team/words` - List words
- `GET /api/{tenant}/team/words/{id}` - Get word details
- `POST /api/{tenant}/team/words` - Create word
- `PUT /api/{tenant}/team/words/{id}` - Update word
- `DELETE /api/{tenant}/team/words/{id}` - Delete word
- `POST /api/{tenant}/team/words/bulk-delete` - Bulk delete
- `POST /api/{tenant}/team/words/bulk` - Bulk import
- `GET /api/{tenant}/team/words/export` - Export words
- `POST /api/{tenant}/team/words/{id}/audio` - Upload word audio
- `POST /api/{tenant}/team/words/{id}/translations` - Add translation
- `PUT /api/{tenant}/team/words/{id}/translations/{tid}` - Update translation
- `DELETE /api/{tenant}/team/words/{id}/translations/{tid}` - Delete translation
- `POST /api/{tenant}/team/words/{id}/translations/{tid}/audio` - Upload translation audio
- `GET /api/{tenant}/team/words/available[/{type}]` - Get available words
- `POST /api/{tenant}/team/words/validate-constraints` - Validate constraints

## Notes

- All file uploads support MP3 and WAV formats with a maximum size of 10MB
- Bulk operations provide detailed error reporting for individual items
- Word constraints validation helps ensure exercise compatibility
- Translation management supports multiple languages per word
- Audio files are processed and optimized by the backend AudioProcessingService