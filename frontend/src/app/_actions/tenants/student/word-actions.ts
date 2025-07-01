"use server";

import axiosInstance from "@/lib/axios";
import { ApiResponse } from "@/lib/axios";
import { WordData } from "@/types/tenant/guidebook";

interface ApiError {
  message: string;
  status: number;
}

function isAxiosError(
  error: unknown
): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === "object" && "isAxiosError" in error;
}

// Student Word Types
interface StudentWord {
  id: number;
  text: string;
  pronunciation_key?: string;
  part_of_speech?: string;
  language: {
    id: number;
    code: string;
    name: string;
  };
  has_audio: boolean;
  audio_url?: string;
  translations_count?: number;
  created_at?: string;
  updated_at?: string;
}

interface StudentWordTranslation {
  id: number;
  language: {
    id: number;
    code: string;
    name: string;
  };
  text: string;
  pronunciation_key?: string;
  context_notes?: string;
  usage_examples?: string[];
  has_audio: boolean;
  audio_url?: string;
}

interface StudentWordDetails extends StudentWord {
  translations: StudentWordTranslation[];
}

interface WordFilters {
  language_id?: number;
  search?: string;
  part_of_speech?: string;
  page?: number;
  per_page?: number;
  sort_by?: 'text' | 'created_at' | 'updated_at';
  sort_direction?: 'asc' | 'desc';
}

interface PaginatedWordsResponse {
  data: StudentWord[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
  };
}

/**
 * Get paginated words with filters for students
 */
export async function getWords(filters: WordFilters = {}) {
  try {
    const params = new URLSearchParams();

    if (filters.language_id) params.append('language_id', filters.language_id.toString());
    if (filters.search) params.append('search', filters.search);
    if (filters.part_of_speech) params.append('part_of_speech', filters.part_of_speech);
    if (filters.page) params.append('page', filters.page.toString());
    if (filters.per_page) params.append('per_page', filters.per_page.toString());
    if (filters.sort_by) params.append('sort_by', filters.sort_by);
    if (filters.sort_direction) params.append('sort_direction', filters.sort_direction);

    const response = await axiosInstance.get<ApiResponse<PaginatedWordsResponse>>(
      `/words?${params.toString()}`
    );

    return {
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || "Failed to fetch words",
      };
    }
    return {
      data: null,
      error: "An unexpected error occurred",
    };
  }
}

/**
 * Fetch words by language (legacy compatibility)
 */
export async function getWordsByLanguage(languageCode: string) {
  // For backward compatibility, we'll need to map language code to language_id
  // This would require a language lookup first, but for now we'll use search
  return getWords({ search: languageCode });
}

/**
 * Fetch a specific word with detailed information for students
 */
export async function getWord(wordId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<StudentWordDetails>>(
      `/words/${wordId}`
    );
    return {
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || "Failed to fetch word",
      };
    }
    return {
      data: null,
      error: "An unexpected error occurred",
    };
  }
}

/**
 * Fetch translations for a word
 */
export async function getWordTranslations(
  wordId: number,
  options: {
    targetLanguageId?: number;
    includeAudio?: boolean;
  } = {}
) {
  try {
    const params = new URLSearchParams();

    if (options.targetLanguageId) {
      params.append('target_language_id', options.targetLanguageId.toString());
    }
    if (options.includeAudio !== undefined) {
      params.append('include_audio', options.includeAudio.toString());
    }

    const url = `/words/${wordId}/translations${params.toString() ? `?${params.toString()}` : ''}`;
    const response = await axiosInstance.get<ApiResponse<StudentWordTranslation[]>>(url);

    return {
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || "Failed to fetch word translations",
      };
    }
    return {
      data: null,
      error: "An unexpected error occurred",
    };
  }
}

/**
 * Fetch words by batch using word IDs
 */
export async function getWordsByBatch(
  wordIds: number[],
  options: {
    includeTranslations?: boolean;
    includeAudio?: boolean;
    targetLanguageId?: number;
  } = {}
) {
  try {
    const payload = {
      word_ids: wordIds,
      include_translations: options.includeTranslations ?? true,
      include_audio: options.includeAudio ?? true,
      ...(options.targetLanguageId && { target_language_id: options.targetLanguageId }),
    };

    const response = await axiosInstance.post<ApiResponse<StudentWord[]>>(
      `/words/batch`,
      payload
    );

    return {
      data: response.data.data || [],
      error: null,
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: [],
        error: error.response?.data?.message || "Failed to fetch words by batch",
      };
    }
    return {
      data: [],
      error: "An unexpected error occurred",
    };
  }
}

/**
 * Legacy function for backward compatibility
 */
export async function getWordsByBatchLegacy(
  words: string[],
  languageCode?: string,
  targetLanguageCode?: string
): Promise<Record<string, WordData>> {
  try {
    // This would need to be adapted based on your existing WordData type
    // For now, returning empty object for compatibility
    console.warn("getWordsByBatchLegacy is deprecated. Use getWordsByBatch with word IDs instead.");
    return {};
  } catch (error) {
    console.error("Error fetching words by batch:", error);
    return {};
  }
}

/**
 * Extract words from text
 */
export function extractWordsFromText(text: string): string[] {
  // Split text into words and remove punctuation
  return text
    .split(/\s+/)
    .map((word) => word.replace(/[.,!?;:'"()]/g, "").toLowerCase())
    .filter((word) => word.length > 0);
}

/**
 * Get words for exercise content (searches by text)
 */
export async function getWordsForExercise(exercise: {
  content?: { question?: string; options?: string[]; explanation?: string }[];
}): Promise<StudentWord[]> {
  try {
    // Extract all words from questions, options, and explanations
    const allWords = new Set<string>();

    if (exercise.content) {
      exercise.content.forEach((item) => {
        if (item.question) {
          extractWordsFromText(item.question).forEach((word) => allWords.add(word));
        }

        if (item.options) {
          item.options.forEach((option) => {
            extractWordsFromText(option).forEach((word) => allWords.add(word));
          });
        }

        if (item.explanation) {
          extractWordsFromText(item.explanation).forEach((word) => allWords.add(word));
        }
      });
    }

    // If no words found, return empty array
    if (allWords.size === 0) {
      return [];
    }

    // Search for words by text (this would need to be implemented as a search endpoint)
    // For now, we'll return empty array and recommend using the search functionality
    console.warn("getWordsForExercise needs word search by text functionality. Use getWords with search parameter instead.");
    return [];
  } catch (error) {
    console.error("Error getting words for exercise:", error);
    return [];
  }
}

/**
 * Search words by text for students
 */
export async function searchWords(
  searchTerm: string,
  options: {
    languageId?: number;
    partOfSpeech?: string;
    limit?: number;
  } = {}
) {
  return getWords({
    search: searchTerm,
    language_id: options.languageId,
    part_of_speech: options.partOfSpeech,
    per_page: options.limit || 20,
  });
}

/**
 * Get word pronunciation audio URL
 */
export function getWordAudioUrl(word: StudentWord): string | null {
  return word.has_audio && word.audio_url ? word.audio_url : null;
}

/**
 * Get translation pronunciation audio URL
 */
export function getTranslationAudioUrl(translation: StudentWordTranslation): string | null {
  return translation.has_audio && translation.audio_url ? translation.audio_url : null;
}

/**
 * Check if word has pronunciation audio
 */
export function hasWordAudio(word: StudentWord): boolean {
  return word.has_audio && !!word.audio_url;
}

/**
 * Check if translation has pronunciation audio
 */
export function hasTranslationAudio(translation: StudentWordTranslation): boolean {
  return translation.has_audio && !!translation.audio_url;
}

/**
 * Format pronunciation key for display
 */
export function formatPronunciation(pronunciationKey?: string): string {
  if (!pronunciationKey) return '';
  return pronunciationKey.startsWith('/') && pronunciationKey.endsWith('/')
    ? pronunciationKey
    : `/${pronunciationKey}/`;
}

/**
 * Get word display text with pronunciation
 */
export function getWordDisplayText(word: StudentWord): string {
  const pronunciation = formatPronunciation(word.pronunciation_key);
  return pronunciation ? `${word.text} ${pronunciation}` : word.text;
}

/**
 * Get translation display text with pronunciation
 */
export function getTranslationDisplayText(translation: StudentWordTranslation): string {
  const pronunciation = formatPronunciation(translation.pronunciation_key);
  return pronunciation ? `${translation.text} ${pronunciation}` : translation.text;
}
