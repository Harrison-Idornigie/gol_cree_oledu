'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/types/learning-path';
import { revalidatePath } from 'next/cache';

/**
 * Submit a transcript for a listening exercise
 */
export async function submitListeningTranscript(
  exerciseId: number,
  transcript: string
) {
  try {
    const response = await axiosInstance.post<
      ApiResponse<{
        is_correct: boolean;
        feedback: string;
        hint: string | null;
        attempt_number: number;
      }>
    >('/api/exercises/listening/check', {
      exercise_id: exerciseId,
      transcript: transcript,
    });

    return {
      success: true,
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error('Error submitting listening transcript:', error);
    return {
      success: false,
      data: null,
      error: 'Failed to submit transcript',
    };
  }
}

/**
 * Get all listening exercises for a specific language
 */
export async function getListeningExercises(languageCode: string) {
  try {
    const response = await axiosInstance.get<
      ApiResponse<{
        exercises: Array<{
          id: number;
          title: string;
          prompt: string;
          audio_url: string;
          difficulty: string;
          is_completed: boolean;
          attempts: number;
          latest_attempt: {
            transcript: string;
            is_correct: boolean;
            attempt_number: number;
            created_at: string;
          } | null;
        }>;
      }>
    >(`/api/exercises/listening/language/${languageCode}`);

    return {
      success: true,
      data: response.data.data.exercises,
      error: null,
    };
  } catch (error) {
    console.error('Error getting listening exercises:', error);
    return {
      success: false,
      data: [],
      error: 'Failed to get listening exercises',
    };
  }
}
