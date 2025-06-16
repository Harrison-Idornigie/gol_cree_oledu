'use server';

import { revalidatePath } from 'next/cache';
import axiosInstance, { setServerAuthToken } from '@/lib/axios';

function isAxiosError(error: unknown): error is { response?: { data?: { message?: string } } } {
  return error != null && typeof error === 'object' && 'isAxiosError' in error;
}

// Types
interface Language {
  id: number;
  code: string;
  name: string;
  native_name?: string;
  direction?: 'ltr' | 'rtl';
  status?: 'active' | 'inactive';
  words_count?: number;
  translations_count?: number;
}

interface ApiResponse<T> {
  data: T;
  message: string;
  success: boolean;
}

interface LanguageFilters {
  with_words_count?: boolean;
  with_translations_count?: boolean;
  status?: 'active' | 'inactive';
}

/**
 * Get all languages for the current tenant
 */
export async function getLanguages(filters: LanguageFilters = {}) {
  try {
    const config = {
      url: '/team/languages',
      params: {
        with_words_count: filters.with_words_count,
        with_translations_count: filters.with_translations_count,
        status: filters.status,
      },
      headers: {} as Record<string, string>
    };

    console.log('[getLanguages] Before setServerAuthToken:', config);
    await setServerAuthToken(config);
    console.log('[getLanguages] After setServerAuthToken:', config);

    const response = await axiosInstance.get<ApiResponse<Language[]>>(config.url, {
      params: config.params,
      headers: config.headers
    });

    console.log('[getLanguages] Response:', response);
    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    console.error('[getLanguages] Error:', error);
    if (isAxiosError(error)) {
      console.error('[getLanguages] Axios error details:', {
        status: error.response?.status,
        data: error.response?.data,
        message: error.response?.data?.message
      });
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch languages'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Get a specific language by ID
 */
export async function getLanguage(id: number) {
  try {
    const config = {
      url: `/team/languages/${id}`,
      headers: {} as Record<string, string>
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.get<ApiResponse<Language>>(config.url, {
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
        error: error.response?.data?.message || 'Failed to fetch language'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Create a new language
 */
export async function createLanguage(languageData: {
  code: string;
  name: string;
  native_name: string;
  direction?: 'ltr' | 'rtl';
  status?: 'active' | 'inactive';
}) {
  try {
    const config = {
      url: '/team/languages',
      headers: {
        'Content-Type': 'application/json'
      } as Record<string, string>
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.post<ApiResponse<Language>>(config.url, languageData, {
      headers: config.headers
    });

    // Revalidate relevant paths
    revalidatePath('/team/languages');
    revalidatePath('/team/words');

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to create language'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Update an existing language
 */
export async function updateLanguage(id: number, languageData: {
  name?: string;
  native_name?: string;
  direction?: 'ltr' | 'rtl';
  status?: 'active' | 'inactive';
}) {
  try {
    const config = {
      url: `/team/languages/${id}`,
      headers: {
        'Content-Type': 'application/json'
      } as Record<string, string>
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.put<ApiResponse<Language>>(config.url, languageData, {
      headers: config.headers
    });

    // Revalidate relevant paths
    revalidatePath('/team/languages');
    revalidatePath(`/team/languages/${id}`);
    revalidatePath('/team/words');

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update language'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Update language status
 */
export async function updateLanguageStatus(id: number, status: 'active' | 'inactive') {
  try {
    const config = {
      url: `/team/languages/${id}/status`,
      headers: {
        'Content-Type': 'application/json'
      } as Record<string, string>
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.patch<ApiResponse<Language>>(config.url, { status }, {
      headers: config.headers
    });

    // Revalidate relevant paths
    revalidatePath('/team/languages');
    revalidatePath(`/team/languages/${id}`);
    revalidatePath('/team/words');

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update language status'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Create a language pair for translation
 */
export async function createLanguagePair(sourceLanguageId: number, targetLanguageId: number) {
  try {
    const config = {
      url: '/team/languages/pairs',
      headers: {
        'Content-Type': 'application/json'
      } as Record<string, string>
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.post<ApiResponse<any>>(config.url, {
      source_language_id: sourceLanguageId,
      target_language_id: targetLanguageId
    }, {
      headers: config.headers
    });

    // Revalidate relevant paths
    revalidatePath('/team/languages');

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to create language pair'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Delete a language pair
 */
export async function deleteLanguagePair(sourceLanguageId: number, targetLanguageId: number) {
  try {
    const config = {
      url: `/team/languages/pairs/${sourceLanguageId}/${targetLanguageId}`,
      headers: {} as Record<string, string>
    };

    await setServerAuthToken(config);
    await axiosInstance.delete(config.url, {
      headers: config.headers
    });

    // Revalidate relevant paths
    revalidatePath('/team/languages');

    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete language pair'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Update language pair status
 */
export async function updateLanguagePairStatus(
  sourceLanguageId: number, 
  targetLanguageId: number, 
  status: 'active' | 'inactive'
) {
  try {
    const config = {
      url: `/team/languages/pairs/${sourceLanguageId}/${targetLanguageId}/status`,
      headers: {
        'Content-Type': 'application/json'
      } as Record<string, string>
    };

    await setServerAuthToken(config);
    const response = await axiosInstance.patch<ApiResponse<any>>(config.url, { status }, {
      headers: config.headers
    });

    // Revalidate relevant paths
    revalidatePath('/team/languages');

    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update language pair status'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}
