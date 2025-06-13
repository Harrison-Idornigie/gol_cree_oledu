'use server';

import axiosInstance from '@/lib/axios';
import { setServerAuthToken, ApiResponse } from '@/lib/axios';
import { revalidatePath } from 'next/cache';
import {
  Word,
  WordTranslation,
  WordFilters,
  PaginatedWordResponse,
  BulkOperationResult,
  AudioUploadResult
} from '@/types/tenant/word';

interface ApiError {
  message: string;
  status: number;
  errors?: Record<string, string[]>;
}

function isAxiosError(error: unknown): error is { response?: { data?: ApiError; status?: number } } {
  return error != null && typeof error === 'object' && 'isAxiosError' in error;
}

/**
 * Get paginated list of words with filtering options
 */
export async function getWords(filters: WordFilters = {}) {
  try {
    const config = {
      url: '/api/team/words',
      params: {
        search: filters.search,
        language_id: filters.language_id,
        difficulty: filters.difficulty,
        tags: filters.tags,
        part_of_speech: filters.part_of_speech,
        has_audio: filters.has_audio,
        sort_by: filters.sort_by || 'text',
        sort_order: filters.sort_order || 'asc',
        per_page: Math.min(filters.per_page || 15, 100),
        page: filters.page || 1
      },
      headers: {} as Record<string, string>
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.get<ApiResponse<PaginatedWordResponse>>(config.url, {
      params: config.params,
      headers: config.headers
    });

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch words'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Get a single word with full details including translations
 */
export async function getWord(id: number) {
  try {
    const config = {
      url: `/api/team/words/${id}`,
      headers: {}
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.get<ApiResponse<Word>>(config.url, {
      headers: config.headers
    });

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch word'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Create a new word with translations and optional audio
 */
export async function createWord(formData: FormData) {
  try {
    const config = {
      url: '/api/team/words',
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.post<ApiResponse<Word>>(config.url, formData, {
      headers: config.headers
    });

    // Revalidate the words list
    revalidatePath('/team/language/words');

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to create word'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Update an existing word
 */
export async function updateWord(id: number, formData: FormData) {
  try {
    const config = {
      url: `/api/team/words/${id}`,
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.put<ApiResponse<Word>>(config.url, formData, {
      headers: config.headers
    });

    // Revalidate the words list and specific word
    revalidatePath('/team/language/words');
    revalidatePath(`/team/language/words/${id}`);

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update word'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Delete a single word
 */
export async function deleteWord(id: number) {
  try {
    const config = {
      url: `/api/team/words/${id}`,
      headers: {}
    };

    await setServerAuthToken(config);
    await axiosInstance.delete(config.url, {
      headers: config.headers
    });

    // Revalidate the words list
    revalidatePath('/team/language/words');

    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete word'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Bulk delete multiple words
 */
export async function bulkDeleteWords(wordIds: number[]) {
  try {
    const config = {
      url: '/api/team/words/bulk-delete',
      headers: {
        'Content-Type': 'application/json'
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.post<ApiResponse<BulkOperationResult>>(config.url, {
      words: wordIds
    }, {
      headers: config.headers
    });

    // Revalidate the words list
    revalidatePath('/team/language/words');

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to delete words'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Bulk import words from CSV or data
 */
export async function bulkImportWords(formData: FormData) {
  try {
    const config = {
      url: '/api/team/words/bulk',
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.post<ApiResponse<BulkOperationResult>>(config.url, formData, {
      headers: config.headers
    });

    // Revalidate the words list
    revalidatePath('/team/language/words');

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to import words'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Export words to CSV format
 */
export async function exportWords(filters: Omit<WordFilters, 'page' | 'per_page'> = {}) {
  try {
    const config = {
      url: '/api/team/words/export',
      params: {
        search: filters.search,
        language_id: filters.language_id,
        difficulty: filters.difficulty,
        tags: filters.tags,
        part_of_speech: filters.part_of_speech,
        has_audio: filters.has_audio,
        format: 'csv'
      },
      headers: {}
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.get(config.url, {
      params: config.params,
      headers: config.headers,
      responseType: 'blob'
    });

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to export words'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Upload audio file for a word's pronunciation
 */
export async function uploadWordAudio(wordId: number, audioFile: File) {
  try {
    const formData = new FormData();
    formData.append('audio', audioFile);

    const config = {
      url: `/api/team/words/${wordId}/audio`,
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.post<ApiResponse<AudioUploadResult>>(
      config.url, 
      formData, 
      {
        headers: config.headers
      }
    );

    // Revalidate the word
    revalidatePath(`/team/language/words/${wordId}`);

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to upload audio'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Add a translation to an existing word
 */
export async function addTranslation(wordId: number, formData: FormData) {
  try {
    const config = {
      url: `/api/team/words/${wordId}/translations`,
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.post<ApiResponse<WordTranslation>>(config.url, formData, {
      headers: config.headers
    });

    // Revalidate the word
    revalidatePath(`/team/language/words/${wordId}`);

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to add translation'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Update an existing translation
 */
export async function updateTranslation(wordId: number, translationId: number, formData: FormData) {
  try {
    const config = {
      url: `/api/team/words/${wordId}/translations/${translationId}`,
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.put<ApiResponse<WordTranslation>>(config.url, formData, {
      headers: config.headers
    });

    // Revalidate the word
    revalidatePath(`/team/language/words/${wordId}`);

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update translation'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Delete a translation
 */
export async function deleteTranslation(wordId: number, translationId: number) {
  try {
    const config = {
      url: `/api/team/words/${wordId}/translations/${translationId}`,
      headers: {}
    };

    await setServerAuthToken(config);
    await axiosInstance.delete(config.url, {
      headers: config.headers
    });

    // Revalidate the word
    revalidatePath(`/team/language/words/${wordId}`);

    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete translation'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Upload audio for a translation
 */
export async function uploadTranslationAudio(wordId: number, translationId: number, audioFile: File) {
  try {
    const formData = new FormData();
    formData.append('audio', audioFile);

    const config = {
      url: `/api/team/words/${wordId}/translations/${translationId}/audio`,
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.post<ApiResponse<AudioUploadResult>>(
      config.url, 
      formData, 
      {
        headers: config.headers
      }
    );

    // Revalidate the word
    revalidatePath(`/team/language/words/${wordId}`);

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to upload translation audio'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Get available words for exercise/lesson builders
 */
export async function getAvailableWords(options: {
  source_language_id?: number;
  target_language_id?: number;
  difficulty?: 'beginner' | 'intermediate' | 'advanced';
  tags?: string[];
  limit?: number;
  exercise_type?: string;
} = {}) {
  try {
    const config = {
      url: `/api/team/words/available${options.exercise_type ? `/${options.exercise_type}` : ''}`,
      params: {
        source_language_id: options.source_language_id,
        target_language_id: options.target_language_id,
        difficulty: options.difficulty,
        tags: options.tags,
        limit: Math.min(options.limit || 100, 200)
      },
      headers: {}
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.get<ApiResponse<Word[]>>(config.url, {
      params: config.params,
      headers: config.headers
    });

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch available words'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Validate word constraints for exercises/lessons
 */
export async function validateWordConstraints(wordIds: number[], context?: string) {
  try {
    const config = {
      url: '/api/team/words/validate-constraints',
      headers: {
        'Content-Type': 'application/json'
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.post<ApiResponse<{
      valid: boolean;
      warnings: string[];
      suggestions: string[];
    }>>(config.url, {
      word_ids: wordIds,
      context
    }, {
      headers: config.headers
    });

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to validate word constraints'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}