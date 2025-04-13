"use server";

import axiosInstance from "@/lib/axios";
import { ApiResponse } from "@/lib/axios";
import { VocabularyItem, WordData } from "@/types/vocabulary";

interface VocabularyWord {
  id: number;
  word: string;
  definition: string;
  example_sentence: string;
  difficulty_level: string;
}

interface ApiError {
  message: string;
  status: number;
}

function isAxiosError(
  error: unknown
): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === "object" && "isAxiosError" in error;
}

export async function getVocabularyWords() {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyWord[]>>(
      "/api/vocabulary"
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
          error.response?.data?.message || "Failed to fetch vocabulary words",
      };
    }
    return {
      data: null,
      error: "An unexpected error occurred",
    };
  }
}

export async function getVocabularyWord(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyWord>>(
      `/api/vocabulary/${id}`
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
          error.response?.data?.message || "Failed to fetch vocabulary word",
      };
    }
    return {
      data: null,
      error: "An unexpected error occurred",
    };
  }
}

export async function markWordAsLearned(id: number) {
  try {
    const response = await axiosInstance.post<
      ApiResponse<{ success: boolean }>
    >(`/api/vocabulary/${id}/mark-learned`);
    return {
      success: response.data.data.success,
      error: null,
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        success: false,
        error:
          error.response?.data?.message || "Failed to mark word as learned",
      };
    }
    return {
      success: false,
      error: "An unexpected error occurred",
    };
  }
}

export async function getVocabularyByLevel(level: string) {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyWord[]>>(
      `/api/vocabulary/level/${level}`
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
          error.response?.data?.message ||
          "Failed to fetch vocabulary words for the specified level",
      };
    }
    return {
      data: null,
      error: "An unexpected error occurred",
    };
  }
}

/**
 * Fetch vocabulary items by lesson ID
 */
export async function getVocabularyByLesson(
  lessonId: number
): Promise<VocabularyItem[]> {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyItem[]>>(
      `/api/vocabulary?lesson_id=${lessonId}`
    );
    return response.data.data || [];
  } catch (error) {
    console.error("Error fetching vocabulary items:", error);
    return [];
  }
}

/**
 * Fetch vocabulary items by word
 */
export async function getVocabularyByWord(
  word: string
): Promise<VocabularyItem[]> {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyItem[]>>(
      `/api/vocabulary?word=${encodeURIComponent(word)}`
    );
    return response.data.data || [];
  } catch (error) {
    console.error("Error fetching vocabulary items:", error);
    return [];
  }
}

/**
 * Fetch vocabulary items by multiple words
 */
export async function getVocabularyByWords(
  words: string[]
): Promise<Record<string, WordData>> {
  try {
    const response = await axiosInstance.post<ApiResponse<VocabularyItem[]>>(
      `/api/vocabulary/batch`,
      { words }
    );

    // Convert the array of vocabulary items to a dictionary keyed by word
    const wordDictionary: Record<string, WordData> = {};

    (response.data.data || []).forEach((item: VocabularyItem) => {
      wordDictionary[item.word.toLowerCase()] = {
        id: item.id,
        text: item.word,
        translation: item.translation,
        phonetic: item.phonetic,
        audioUrl: item.pronunciation_url,
        partOfSpeech: item.part_of_speech,
        example: item.example,
      };
    });

    return wordDictionary;
  } catch (error) {
    console.error("Error fetching vocabulary items:", error);
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
 * Get vocabulary for exercise content
 */
export async function getVocabularyForExercise(exercise: {
  content?: { question?: string; options?: string[]; explanation?: string }[];
}): Promise<Record<string, WordData>> {
  try {
    // Extract all words from questions, options, and explanations
    const allWords = new Set<string>();

    if (exercise.content && Array.isArray(exercise.content)) {
      exercise.content.forEach((question) => {
        // Extract words from question text
        if (question.question) {
          extractWordsFromText(question.question).forEach((word) =>
            allWords.add(word)
          );
        }

        // Extract words from options
        if (question.options && Array.isArray(question.options)) {
          question.options.forEach((option: string) => {
            extractWordsFromText(option).forEach((word) => allWords.add(word));
          });
        }

        // Extract words from explanation
        if (question.explanation) {
          extractWordsFromText(question.explanation).forEach((word) =>
            allWords.add(word)
          );
        }
      });
    }

    // Fetch vocabulary data for all words
    return await getVocabularyByWords(Array.from(allWords));
  } catch (error) {
    console.error("Error getting vocabulary for exercise:", error);
    return {};
  }
}
