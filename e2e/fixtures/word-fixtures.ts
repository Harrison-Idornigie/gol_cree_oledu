import { DatabaseHelper } from '../utils/database-helpers';

/**
 * Word Test Fixtures for E2E Tests
 * 
 * Provides reusable test data and utilities for word management testing
 * across different test scenarios.
 */
export class WordFixtures {
  private dbHelper: DatabaseHelper;

  constructor() {
    this.dbHelper = new DatabaseHelper();
  }

  /**
   * Generate valid word data for testing
   */
  generateValidWordData(overrides: Partial<WordData> = {}): WordData {
    const timestamp = Date.now();
    const randomSuffix = Math.random().toString(36).substring(2, 6);
    
    return {
      language_id: 1,
      text: `test-word-${timestamp}-${randomSuffix}`,
      pronunciation_key: `test-wurd-${randomSuffix}`,
      part_of_speech: 'noun',
      metadata: {
        difficulty: 'beginner',
        tags: ['test'],
        frequency: 'common'
      },
      ...overrides
    };
  }

  /**
   * Generate invalid word data for validation testing
   */
  generateInvalidWordData(type: InvalidWordType): WordData {
    const base = this.generateValidWordData();

    switch (type) {
      case 'missing_required':
        return {
          ...base,
          language_id: undefined as any,
          text: '',
          part_of_speech: ''
        };

      case 'invalid_types':
        return {
          ...base,
          language_id: 'not-a-number' as any,
          text: 123 as any,
          part_of_speech: ['array-not-string'] as any
        };

      case 'invalid_part_of_speech':
        return {
          ...base,
          part_of_speech: 'invalid-pos'
        };

      case 'text_too_long':
        return {
          ...base,
          text: 'a'.repeat(256) // Assuming 255 char limit
        };

      default:
        return base;
    }
  }

  /**
   * Generate word translation data
   */
  generateValidTranslationData(overrides: Partial<TranslationData> = {}): TranslationData {
    const timestamp = Date.now();
    const randomSuffix = Math.random().toString(36).substring(2, 6);
    
    return {
      language_id: 2,
      text: `translation-${timestamp}-${randomSuffix}`,
      pronunciation_key: `trans-lay-shun-${randomSuffix}`,
      context_notes: `Translation notes for testing ${timestamp}`,
      usage_examples: [
        `Example usage: ${timestamp}`,
        `Another example: ${randomSuffix}`
      ],
      translation_order: 1,
      ...overrides
    };
  }

  /**
   * Generate bulk word data for testing bulk operations
   */
  generateBulkWordData(count: number = 5): WordData[] {
    return Array.from({ length: count }, (_, index) => 
      this.generateValidWordData({
        text: `bulk-word-${Date.now()}-${index}`,
        part_of_speech: index % 2 === 0 ? 'noun' : 'verb'
      })
    );
  }

  /**
   * Generate test data for different parts of speech
   */
  generateWordsWithDifferentPOS(): WordData[] {
    const partsOfSpeech = ['noun', 'verb', 'adjective', 'adverb', 'preposition'];
    
    return partsOfSpeech.map((pos, index) => 
      this.generateValidWordData({
        text: `${pos}-word-${Date.now()}-${index}`,
        part_of_speech: pos
      })
    );
  }

  /**
   * Generate words for different languages
   */
  generateMultiLanguageWords(): WordData[] {
    const languages = [
      { id: 1, code: 'en', word: 'english-word' },
      { id: 2, code: 'es', word: 'palabra-española' },
      { id: 3, code: 'fr', word: 'mot-français' }
    ];

    return languages.map(lang => 
      this.generateValidWordData({
        language_id: lang.id,
        text: `${lang.word}-${Date.now()}`
      })
    );
  }

  /**
   * Generate words with different difficulty levels
   */
  generateWordsByDifficulty(): WordData[] {
    const difficulties = ['beginner', 'intermediate', 'advanced'];
    
    return difficulties.map((difficulty, index) =>
      this.generateValidWordData({
        text: `${difficulty}-word-${Date.now()}-${index}`,
        metadata: {
          difficulty,
          tags: [difficulty, 'test'],
          frequency: difficulty === 'beginner' ? 'very_common' : 'uncommon'
        }
      })
    );
  }

  /**
   * Generate words with different tag combinations
   */
  generateWordsWithTags(): WordData[] {
    const tagCombinations = [
      ['animals', 'nature'],
      ['food', 'cooking'],
      ['technology', 'modern'],
      ['education', 'academic'],
      ['sports', 'activities']
    ];

    return tagCombinations.map((tags, index) =>
      this.generateValidWordData({
        text: `tagged-word-${Date.now()}-${index}`,
        metadata: {
          difficulty: 'intermediate',
          tags,
          frequency: 'common'
        }
      })
    );
  }

  /**
   * Generate word constraint test scenarios
   */
  generateConstraintTestData(): ConstraintTestData {
    return {
      wordsWithAudio: Array.from({ length: 3 }, (_, i) =>
        this.generateValidWordData({
          text: `audio-word-${Date.now()}-${i}`,
          metadata: { has_audio: true, difficulty: 'beginner' }
        })
      ),
      wordsWithoutAudio: Array.from({ length: 3 }, (_, i) =>
        this.generateValidWordData({
          text: `no-audio-word-${Date.now()}-${i}`,
          metadata: { has_audio: false, difficulty: 'intermediate' }
        })
      ),
      beginnerWords: Array.from({ length: 5 }, (_, i) =>
        this.generateValidWordData({
          text: `beginner-word-${Date.now()}-${i}`,
          metadata: { difficulty: 'beginner', frequency: 'very_common' }
        })
      ),
      advancedWords: Array.from({ length: 3 }, (_, i) =>
        this.generateValidWordData({
          text: `advanced-word-${Date.now()}-${i}`,
          metadata: { difficulty: 'advanced', frequency: 'rare' }
        })
      )
    };
  }

  /**
   * Generate test data for search functionality
   */
  generateSearchTestData(): SearchTestData {
    const timestamp = Date.now();
    
    return {
      searchableWords: [
        this.generateValidWordData({
          text: `unique-searchable-term-${timestamp}`,
          part_of_speech: 'noun'
        }),
        this.generateValidWordData({
          text: `another-searchable-word-${timestamp}`,
          part_of_speech: 'verb'
        }),
        this.generateValidWordData({
          text: `searchable-adjective-${timestamp}`,
          part_of_speech: 'adjective'
        })
      ],
      nonSearchableWords: [
        this.generateValidWordData({
          text: `random-word-${timestamp}-1`,
          part_of_speech: 'noun'
        }),
        this.generateValidWordData({
          text: `different-term-${timestamp}-2`,
          part_of_speech: 'verb'
        })
      ]
    };
  }

  /**
   * Generate expected validation error messages
   */
  getExpectedValidationErrors(): Record<string, Record<string, string>> {
    return {
      missing_required: {
        'language_id': 'The language_id field is required.',
        'text': 'The text field is required.',
        'part_of_speech': 'The part_of_speech field is required.'
      },
      invalid_types: {
        'language_id': 'The language_id field must be an integer.',
        'text': 'The text field must be a string.',
        'part_of_speech': 'The part_of_speech field must be a string.'
      },
      invalid_part_of_speech: {
        'part_of_speech': 'The selected part_of_speech is invalid.'
      },
      text_too_long: {
        'text': 'The text field must not be greater than 255 characters.'
      },
      duplicate_text: {
        'text': 'The text has already been taken for this language.'
      }
    };
  }

  /**
   * Generate expected success response structure for word operations
   */
  getExpectedWordResponse(): any {
    return {
      success: true,
      message: 'string',
      data: {
        id: 'number',
        language_id: 'number',
        text: 'string',
        pronunciation_key: 'string',
        part_of_speech: 'string',
        created_at: 'string',
        updated_at: 'string',
        has_audio: 'boolean',
        audio_url: 'string',
        translations_count: 'number'
      }
    };
  }

  /**
   * Generate expected paginated response structure
   */
  getExpectedPaginatedResponse(): any {
    return {
      success: true,
      message: 'string',
      data: {
        data: 'array',
        current_page: 'number',
        per_page: 'number',
        total: 'number',
        last_page: 'number',
        from: 'number',
        to: 'number'
      }
    };
  }

  /**
   * Create mock audio file for testing
   */
  createMockAudioFile(filename: string = 'test-audio.mp3'): File {
    // Create a minimal MP3 file buffer for testing
    const mp3Header = new Uint8Array([
      0xFF, 0xFB, 0x90, 0x00, // MP3 frame header
      0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00,
      0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00
    ]);
    
    return new File([mp3Header], filename, { type: 'audio/mpeg' });
  }

  /**
   * Create mock invalid audio file for testing
   */
  createMockInvalidAudioFile(filename: string = 'test-invalid.txt'): File {
    const textContent = 'This is not an audio file';
    return new File([textContent], filename, { type: 'text/plain' });
  }
}

/**
 * Type definitions for word test data
 */
export interface WordData {
  language_id: number;
  text: string;
  pronunciation_key?: string;
  part_of_speech: string;
  metadata?: {
    difficulty?: string;
    tags?: string[];
    frequency?: string;
    has_audio?: boolean;
    [key: string]: any;
  };
}

export interface TranslationData {
  language_id: number;
  text: string;
  pronunciation_key?: string;
  context_notes?: string;
  usage_examples?: string[];
  translation_order?: number;
}

export interface ConstraintTestData {
  wordsWithAudio: WordData[];
  wordsWithoutAudio: WordData[];
  beginnerWords: WordData[];
  advancedWords: WordData[];
}

export interface SearchTestData {
  searchableWords: WordData[];
  nonSearchableWords: WordData[];
}

export type InvalidWordType = 
  | 'missing_required'
  | 'invalid_types'
  | 'invalid_part_of_speech'
  | 'text_too_long';

/**
 * Common test audio file configurations
 */
export const AUDIO_TEST_CONFIG = {
  validFormats: ['mp3', 'wav'],
  invalidFormats: ['txt', 'jpg', 'pdf'],
  maxFileSize: 10240, // 10MB in KB
  testAudioDuration: 1000 // 1 second in milliseconds
};

/**
 * Common word filtering and search test parameters
 */
export const WORD_TEST_PARAMS = {
  pagination: {
    defaultPerPage: 15,
    maxPerPage: 100,
    testPerPage: 5
  },
  sorting: {
    validSortFields: ['text', 'created_at', 'updated_at', 'part_of_speech'],
    validSortOrders: ['asc', 'desc']
  },
  filtering: {
    validPartsOfSpeech: ['noun', 'verb', 'adjective', 'adverb', 'preposition', 'conjunction', 'interjection'],
    validDifficulties: ['beginner', 'intermediate', 'advanced']
  }
};