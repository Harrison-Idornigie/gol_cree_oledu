"use server";

import { Exercise } from "@/types/tenant/exercises";
import { WordData } from "@/types/tenant/vocabulary";
import axiosInstance, { ApiResponse } from "@/lib/axios";

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
 * Fetch exercises for a specific lesson
 */
export async function getExercisesForLesson(
  lessonId: number
): Promise<Exercise[]> {
  try {
    const response = await axiosInstance.get<ApiResponse<Exercise[]>>(
      `/api/v1/lessons/${lessonId}/exercises`,
      {
        headers: {
          "Cache-Control": "max-age=60", // Cache for 60 seconds
        },
      }
    );

    return response.data.data;
  } catch (error) {
    console.error("Error fetching exercises:", error);
    if (isAxiosError(error)) {
      throw new Error(
        error.response?.data?.message || "Failed to fetch exercises"
      );
    }
    throw new Error("An unexpected error occurred");
  }
}

/**
 * Fetch word data for a list of word IDs
 */
export async function getWordsForExercises(
  wordIds: number[]
): Promise<Record<string, WordData>> {
  try {
    if (!wordIds.length) {
      return {};
    }

    const response = await axiosInstance.post<ApiResponse<WordData[]>>(
      `/api/v1/words/batch`,
      { ids: wordIds },
      {
        headers: {
          "Cache-Control": "max-age=3600", // Cache for 1 hour
        },
      }
    );

    // Convert array to record keyed by word text for easy lookup
    const wordData: Record<string, WordData> = {};
    response.data.data.forEach((word: WordData) => {
      wordData[word.text.toLowerCase()] = word;
    });

    return wordData;
  } catch (error) {
    console.error("Error fetching word data:", error);
    if (isAxiosError(error)) {
      throw new Error(
        error.response?.data?.message || "Failed to fetch word data"
      );
    }
    throw new Error("An unexpected error occurred");
  }
}

/**
 * Check an exercise answer
 */
export async function checkExerciseAnswer(
  exerciseId: number,
  answer: string | string[] | Record<string, string | number>,
  startedAt: Date
): Promise<{
  correct: boolean;
  feedback: string;
  correctAnswer?: string | string[] | Record<string, string | number>;
  timeTaken: number;
}> {
  try {
    const response = await axiosInstance.post<
      ApiResponse<{
        correct: boolean;
        feedback: string;
        correct_answer?: string | string[] | Record<string, string | number>;
        time_taken: number;
      }>
    >(
      `/api/v1/exercises/${exerciseId}/check`,
      {
        answer,
        started_at: startedAt.toISOString(),
      },
      {
        headers: {
          "Cache-Control": "no-store",
        },
      }
    );

    return {
      correct: response.data.data.correct,
      feedback: response.data.data.feedback,
      correctAnswer: response.data.data.correct_answer,
      timeTaken: response.data.data.time_taken,
    };
  } catch (error) {
    console.error("Error checking exercise answer:", error);
    if (isAxiosError(error)) {
      throw new Error(
        error.response?.data?.message || "Failed to check answer"
      );
    }
    throw new Error("An unexpected error occurred");
  }
}
