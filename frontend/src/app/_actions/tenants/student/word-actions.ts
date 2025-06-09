"use server";

import axiosInstance from "@/lib/axios";
import { ApiResponse } from "@/lib/axios";
import { WordData } from "@/types/vocabulary";

interface ApiError {
  message: string;
  status: number;
}

function isAxiosError(
  error: unknown
): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === "object" && "isAxiosError" in error;
}

/**
 * Fetch words by language
 */
export async function getWordsByLanguage(languageCode: string) {
  try {
    const response = await axiosInstance.get<ApiResponse<any>>(
      `/api/words?language_code=${languageCode}`
    );
    return {
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error:
          error.response?.data?.message || "Failed to fetch words",
      };
    }
    return {
      data: null,
      error: "An unexpected error occurred",
    };
  }
}

/**
 * Fetch a specific word with translations
 */
export async function getWord(wordId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<any>>(
      `/api/words/${wordId}`
    );
    return {
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error:
          error.response?.data?.message || "Failed to fetch word",
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
export async function getWordTranslations(wordId: number, targetLanguageCode?: string) {
  try {
    let url = `/api/words/${wordId}/translations`;
    if (targetLanguageCode) {
      url += `?language_code=${targetLanguageCode}`;
    }
    
    const response = await axiosInstance.get<ApiResponse<any>>(url);
    return {
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error:
          error.response?.data?.message || "Failed to fetch word translations",
      };
    }
    return {
      data: null,
      error: "An unexpected error occurred",
    };
  }
}

/**
 * Fetch words by batch
 */
export async function getWordsByBatch(
  words: string[],
  languageCode?: string,
  targetLanguageCode?: string
): Promise<Record<string, WordData>> {
  try {
    const payload: any = { words };
    
    if (languageCode) {
      payload.language_code = languageCode;
    }
    
    if (targetLanguageCode) {
      payload.target_language_code = targetLanguageCode;
    }
    
    const response = await axiosInstance.post<ApiResponse<Record<string, WordData>>>(
      `/api/words/batch`,
      payload
    );

    return response.data.data || {};
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
 * Get words for exercise content
 */
export async function getWordsForExercise(exercise: {
  content?: { question?: string; options?: string[]; explanation?: string }[];
}): Promise<Record<string, WordData>> {
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
    
    // If no words found, return empty object
    if (allWords.size === 0) {
      return {};
    }
    
    // Get words data
    return await getWordsByBatch(Array.from(allWords));
  } catch (error) {
    console.error("Error getting words for exercise:", error);
    return {};
  }
}
