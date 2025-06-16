import { axiosInstance, setServerAuthToken, isAxiosError } from '@/lib/axios';
import { ApiResponse } from '@/types/api';

export interface Sentence {
  id: number;
  language_id: number;
  text: string;
  pronunciation_key?: string;
  metadata?: {
    difficulty?: string;
    tags?: string[];
    notes?: string;
  };
  words_count?: number;
  translations_count?: number;
  has_audio?: boolean;
  created_at: string;
  updated_at: string;
}

export interface SentenceWord {
  id: string | number;
  text: string;
  type: 'word' | 'exception';
  exception_type?: string;
  description?: string;
  part_of_speech?: string;
  translations?: Array<{
    id: number;
    text: string;
    language_code: string;
  }>;
}

export interface SentenceValidation {
  valid: boolean;
  errors: string[];
  warnings: string[];
  suggestions: string[];
}

/**
 * Get paginated sentences with filters
 */
export async function getSentences(options: {
  search?: string;
  language_id?: number;
  difficulty?: string;
  has_audio?: boolean;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
} = {}) {
  try {
    const config = {
      url: '/team/sentences',
      params: {
        search: options.search,
        language_id: options.language_id,
        difficulty: options.difficulty,
        has_audio: options.has_audio,
        sort_by: options.sort_by || 'created_at',
        sort_order: options.sort_order || 'desc',
        per_page: Math.min(options.per_page || 15, 50),
        page: options.page || 1
      },
      headers: {}
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.get<ApiResponse<{
      sentences: Sentence[];
      pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
      };
    }>>(config.url, {
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
        error: error.response?.data?.message || 'Failed to fetch sentences'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Create a new sentence
 */
export async function createSentence(formData: FormData) {
  try {
    const config = {
      url: '/team/sentences',
      headers: {
        // Don't set Content-Type for FormData, let browser set it with boundary
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.post<ApiResponse<{
      sentence: Sentence;
    }>>(config.url, formData, {
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
        error: error.response?.data?.message || 'Failed to create sentence'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Get available words for sentence creation
 */
export async function getAvailableWords(options: {
  language_id: number;
  search?: string;
  limit?: number;
} = { language_id: 0 }) {
  try {
    const config = {
      url: '/team/sentences/available-words',
      params: {
        language_id: options.language_id,
        search: options.search || '',
        limit: Math.min(options.limit || 50, 100)
      },
      headers: {}
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.get<ApiResponse<{
      words: SentenceWord[];
    }>>(config.url, {
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
 * Validate sentence words before creation
 */
export async function validateSentenceWords(data: {
  text: string;
  language_id: number;
  words?: Array<{
    word_id: string | number;
    position: number;
  }>;
}) {
  try {
    const config = {
      url: '/team/sentences/validate-words',
      headers: {
        'Content-Type': 'application/json'
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.post<ApiResponse<SentenceValidation>>(
      config.url,
      data,
      {
        headers: config.headers
      }
    );

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to validate sentence'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Get sentence details
 */
export async function getSentence(id: number) {
  try {
    const config = {
      url: `/team/sentences/${id}`,
      headers: {}
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.get<ApiResponse<{
      sentence: Sentence;
    }>>(config.url, {
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
        error: error.response?.data?.message || 'Failed to fetch sentence'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Update an existing sentence
 */
export async function updateSentence(id: number, formData: FormData) {
  try {
    const config = {
      url: `/team/sentences/${id}`,
      headers: {
        // Don't set Content-Type for FormData
      }
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.put<ApiResponse<{
      sentence: Sentence;
    }>>(config.url, formData, {
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
        error: error.response?.data?.message || 'Failed to update sentence'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Delete a sentence
 */
export async function deleteSentence(id: number) {
  try {
    const config = {
      url: `/team/sentences/${id}`,
      headers: {}
    };

    await setServerAuthToken(config);
    await axiosInstance.delete(config.url, {
      headers: config.headers
    });

    return {
      data: { success: true },
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to delete sentence'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}
