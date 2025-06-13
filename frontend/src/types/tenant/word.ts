/**
 * Team Word Management Types
 * 
 * These types define the data structures used for word management
 * in the team portal, including words, translations, and related operations.
 */

// Core Word Types
export interface WordTranslation {
  id?: number;
  language_id: number;
  language_code?: string;
  text: string;
  pronunciation_key?: string;
  context_notes?: string;
  usage_examples?: string[];
  translation_order?: number;
  pronunciation_url?: string;
  audio_file?: File;
}

export interface Word {
  id: number;
  language_id: number;
  language_code?: string;
  text: string;
  pronunciation_key?: string;
  part_of_speech?: string;
  metadata?: Record<string, unknown>;
  has_audio?: boolean;
  audio_url?: string;
  pronunciation_url?: string;
  translations_count?: number;
  translations?: WordTranslation[];
  created_at: string;
  updated_at: string;
}

// Filter and Query Types
export interface WordFilters {
  search?: string;
  language_id?: number;
  difficulty?: string;
  tags?: string[];
  part_of_speech?: string;
  has_audio?: boolean;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

export interface WordQueryOptions {
  source_language_id?: number;
  target_language_id?: number;
  difficulty?: 'beginner' | 'intermediate' | 'advanced';
  tags?: string[];
  limit?: number;
  exercise_type?: string;
}

// Response Types
export interface PaginatedWordResponse {
  data: Word[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

export interface BulkOperationResult {
  success: boolean;
  processed: number;
  errors: Array<{ row?: number; id?: number; message: string }>;
  created?: number;
  updated?: number;
  deleted?: number;
}

export interface WordConstraintValidation {
  valid: boolean;
  warnings: string[];
  suggestions: string[];
}

export interface AudioUploadResult {
  audio_url: string;
  duration?: number;
  format?: string;
  size?: number;
}

// Form Data Types
export interface CreateWordFormData {
  language_id: number;
  text: string;
  pronunciation_key?: string;
  part_of_speech?: string;
  metadata?: Record<string, unknown>;
  pronunciation_audio?: File;
  translations?: Omit<WordTranslation, 'id'>[];
}

export interface UpdateWordFormData extends Partial<CreateWordFormData> {
  id: number;
}

export interface CreateTranslationFormData {
  language_id: number;
  text: string;
  pronunciation_key?: string;
  context_notes?: string;
  usage_examples?: string[];
  translation_order?: number;
  pronunciation_audio?: File;
}

export interface UpdateTranslationFormData extends Partial<CreateTranslationFormData> {
  id: number;
}

// Import/Export Types
export interface WordImportData {
  text: string;
  language_code: string;
  pronunciation_key?: string;
  part_of_speech?: string;
  translations?: Array<{
    text: string;
    language_code: string;
    pronunciation_key?: string;
    context_notes?: string;
  }>;
}

export interface WordExportFilters extends Omit<WordFilters, 'page' | 'per_page'> {
  format?: 'csv' | 'json' | 'xlsx';
  include_translations?: boolean;
  include_audio_urls?: boolean;
}

// Error Types
export interface WordValidationError {
  field: string;
  message: string;
  value?: unknown;
}

export interface WordOperationError {
  message: string;
  status?: number;
  errors?: Record<string, string[]>;
  word_id?: number;
  translation_id?: number;
}

// Component State Types
export interface WordListState {
  words: Word[];
  loading: boolean;
  error: string | null;
  filters: WordFilters;
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  selectedWords: number[];
}

export interface WordFormState {
  word: Partial<Word>;
  translations: Partial<WordTranslation>[];
  loading: boolean;
  error: string | null;
  isDirty: boolean;
  validationErrors: Record<string, string>;
}

export interface AudioUploadState {
  uploading: boolean;
  progress: number;
  error: string | null;
  result: AudioUploadResult | null;
}

// Constants
export const WORD_PARTS_OF_SPEECH = [
  'noun',
  'verb',
  'adjective',
  'adverb',
  'pronoun',
  'preposition',
  'conjunction',
  'interjection',
  'article',
  'determiner'
] as const;

export const WORD_DIFFICULTY_LEVELS = [
  'beginner',
  'intermediate',
  'advanced'
] as const;

export const WORD_SORT_OPTIONS = [
  { value: 'text', label: 'Alphabetical' },
  { value: 'created_at', label: 'Date Created' },
  { value: 'updated_at', label: 'Date Modified' },
  { value: 'translations_count', label: 'Translation Count' }
] as const;

export const SUPPORTED_AUDIO_FORMATS = [
  'audio/mpeg',
  'audio/mp3',
  'audio/wav',
  'audio/ogg'
] as const;

export const MAX_AUDIO_FILE_SIZE = 10 * 1024 * 1024; // 10MB

// Type Guards
export function isWord(obj: unknown): obj is Word {
  return (
    typeof obj === 'object' &&
    obj !== null &&
    'id' in obj &&
    'text' in obj &&
    'language_id' in obj &&
    typeof (obj as Word).id === 'number' &&
    typeof (obj as Word).text === 'string' &&
    typeof (obj as Word).language_id === 'number'
  );
}

export function isWordTranslation(obj: unknown): obj is WordTranslation {
  return (
    typeof obj === 'object' &&
    obj !== null &&
    'text' in obj &&
    'language_id' in obj &&
    typeof (obj as WordTranslation).text === 'string' &&
    typeof (obj as WordTranslation).language_id === 'number'
  );
}

export function isValidAudioFile(file: File): boolean {
  return (
    SUPPORTED_AUDIO_FORMATS.includes(file.type as typeof SUPPORTED_AUDIO_FORMATS[number]) &&
    file.size <= MAX_AUDIO_FILE_SIZE
  );
}

// Utility Types
export type WordPartOfSpeech = typeof WORD_PARTS_OF_SPEECH[number];
export type WordDifficultyLevel = typeof WORD_DIFFICULTY_LEVELS[number];
export type WordSortField = typeof WORD_SORT_OPTIONS[number]['value'];
export type SupportedAudioFormat = typeof SUPPORTED_AUDIO_FORMATS[number];