'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/types/learning-path';
import { revalidatePath } from 'next/cache';

/**
 * Submit an answer for a picture exercise
 */
export async function submitPictureAnswer(
  exerciseId: number,
  selectedOption: number
) {
  try {
    const response = await axiosInstance.post<
      ApiResponse<{
        is_correct: boolean;
        feedback: string;
        attempt_number: number;
      }>
    >('/api/exercises/picture/check', {
      exercise_id: exerciseId,
      selected_option: selectedOption,
    });

    return {
      success: true,
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error('Error submitting picture answer:', error);
    return {
      success: false,
      data: null,
      error: 'Failed to submit answer',
    };
  }
}

/**
 * Get all picture exercises for a specific language
 */
export async function getPictureExercises(languageCode: string) {
  try {
    const response = await axiosInstance.get<
      ApiResponse<{
        exercises: Array<{
          id: number;
          title: string;
          question: string;
          mode: 'word_to_image' | 'image_to_word';
          images: Array<{ url: string; alt: string }>;
          words: string[];
          target_image: string;
          is_completed: boolean;
          attempts: number;
          latest_attempt: {
            selected_option: number;
            is_correct: boolean;
            attempt_number: number;
            created_at: string;
          } | null;
        }>;
      }>
    >(`/api/exercises/picture/language/${languageCode}`);

    return {
      success: true,
      data: response.data.data.exercises,
      error: null,
    };
  } catch (error) {
    console.error('Error getting picture exercises:', error);
    return {
      success: false,
      data: [],
      error: 'Failed to get picture exercises',
    };
  }
}
