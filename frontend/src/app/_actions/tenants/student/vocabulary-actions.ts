"use server";

import axiosInstance from "@/lib/axios";
import { ApiResponse } from "@/lib/axios";
import { VocabularyItem, WordData } from "@/types/tenant/vocabulary";

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
 * Get vocabulary items for review
 */
export async function getVocabularyReviewItems(
  count: number = 10,
  options?: {
    difficulty?: number;
    unitId?: number;
    languageId?: number;
    reviewType?: "due" | "mistakes" | "all";
  }
): Promise<VocabularyItem[]> {
  try {
    const params = new URLSearchParams();
    params.append("count", count.toString());

    if (options?.difficulty) {
      params.append("difficulty", options.difficulty.toString());
    }

    if (options?.unitId) {
      params.append("unit_id", options.unitId.toString());
    }

    if (options?.languageId) {
      params.append("language_id", options.languageId.toString());
    }

    if (options?.reviewType) {
      params.append("review_type", options.reviewType);
    }

    const response = await axiosInstance.get<ApiResponse<VocabularyItem[]>>(
      `/api/vocabulary/review?${params.toString()}`
    );

    return response.data.data || [];
  } catch (error) {
    console.error("Error fetching vocabulary review items:", error);
    return [];
  }
}

/**
 * Get vocabulary items for a specific unit
 */
export async function getUnitVocabulary(
  unitId: number,
  difficulty?: number
): Promise<VocabularyItem[]> {
  try {
    const params = new URLSearchParams();

    if (difficulty) {
      params.append("difficulty", difficulty.toString());
    }

    const response = await axiosInstance.get<ApiResponse<VocabularyItem[]>>(
      `/api/vocabulary/unit/${unitId}?${params.toString()}`
    );

    return response.data.data || [];
  } catch (error) {
    console.error("Error fetching unit vocabulary:", error);
    return [];
  }
}

/**
 * Get vocabulary items that the user has struggled with
 */
export async function getMistakeVocabularyItems(
  count: number = 10,
  options?: {
    unitId?: number;
    languageId?: number;
  }
): Promise<VocabularyItem[]> {
  try {
    const params = new URLSearchParams();
    params.append("count", count.toString());

    if (options?.unitId) {
      params.append("unit_id", options.unitId.toString());
    }

    if (options?.languageId) {
      params.append("language_id", options.languageId.toString());
    }

    const response = await axiosInstance.get<ApiResponse<VocabularyItem[]>>(
      `/api/vocabulary/mistakes?${params.toString()}`
    );

    return response.data.data || [];
  } catch (error) {
    console.error("Error fetching mistake vocabulary items:", error);
    return [];
  }
}

/**
 * Check a vocabulary translation
 */
export async function checkVocabularyTranslation(
  vocabularyId: number,
  translation: string
): Promise<{
  correct: boolean;
  correctTranslation?: string;
  similarWords?: VocabularyItem[];
}> {
  try {
    const response = await axiosInstance.post<
      ApiResponse<{
        correct: boolean;
        correct_translation?: string;
        similar_words?: VocabularyItem[];
      }>
    >(`/api/vocabulary/${vocabularyId}/check`, { translation });

    return {
      correct: response.data.data.correct,
      correctTranslation: response.data.data.correct_translation,
      similarWords: response.data.data.similar_words,
    };
  } catch (error) {
    console.error("Error checking vocabulary translation:", error);
    return { correct: false };
  }
}

/**
 * Get vocabulary statistics
 */
export async function getVocabularyStatistics(options?: {
  unitId?: number;
  languageId?: number;
}): Promise<{
  total_words_learned: number;
  words_in_progress: number;
  mastery_levels: {
    mastered: number;
    familiar: number;
    learning: number;
  };
  daily_progress: Record<string, { total: number; completed: number }>;
  recent_activity: Array<{
    word: string;
    translation: string;
    status: string;
    correct_streak: number;
    last_review: string;
  }>;
  due_today: number;
  mistakes: number;
  recent_vocabulary: Array<{
    id: number;
    word: string;
    translation: string;
    phonetic?: string;
    example?: string;
    mastery: number;
    review_count: number;
    last_review?: string;
  }>;
}> {
  try {
    const params = new URLSearchParams();

    if (options?.unitId) {
      params.append("unit_id", options.unitId.toString());
    }

    if (options?.languageId) {
      params.append("language_id", options.languageId.toString());
    }

    const response = await axiosInstance.get<
      ApiResponse<{
        total_words_learned: number;
        words_in_progress: number;
        mastery_levels: {
          mastered: number;
          familiar: number;
          learning: number;
        };
        daily_progress: Record<string, { total: number; completed: number }>;
        recent_activity: Array<{
          word: string;
          translation: string;
          status: string;
          correct_streak: number;
          last_review: string;
        }>;
        due_today: number;
        mistakes: number;
        recent_vocabulary: Array<{
          id: number;
          word: string;
          translation: string;
          phonetic?: string;
          example?: string;
          mastery: number;
          review_count: number;
          last_review?: string;
        }>;
      }>
    >(`/api/vocabulary/statistics?${params.toString()}`);

    return response.data.data;
  } catch (error) {
    console.error("Error fetching vocabulary statistics:", error);
    return {
      total_words_learned: 0,
      words_in_progress: 0,
      mastery_levels: {
        mastered: 0,
        familiar: 0,
        learning: 0,
      },
      daily_progress: {},
      recent_activity: [],
      due_today: 0,
      mistakes: 0,
      recent_vocabulary: [],
    };
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
