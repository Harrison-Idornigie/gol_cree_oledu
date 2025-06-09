'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/types/learning-path';
import { revalidatePath } from 'next/cache';

/**
 * Submit an answer for a conversation exercise step
 */
export async function submitConversationAnswer(
  exerciseId: number,
  stepIndex: number,
  answer: string | number
) {
  try {
    const response = await axiosInstance.post<
      ApiResponse<{ is_correct: boolean; feedback: string }>
    >('/api/exercises/conversation/answer', {
      exercise_id: exerciseId,
      step_index: stepIndex,
      answer: answer,
    });

    return {
      success: true,
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error('Error submitting conversation answer:', error);
    return {
      success: false,
      data: null,
      error: 'Failed to submit answer',
    };
  }
}

/**
 * Track progress in a conversation exercise
 */
export async function trackConversationProgress(
  exerciseId: number,
  lastStepCompleted: number,
  isCompleted: boolean
) {
  try {
    const response = await axiosInstance.post<
      ApiResponse<{ success: boolean }>
    >('/api/exercises/conversation/progress', {
      exercise_id: exerciseId,
      last_step_completed: lastStepCompleted,
      is_completed: isCompleted,
    });

    // Revalidate paths that might show progress
    revalidatePath('/student');
    revalidatePath('/student/paths');

    return {
      success: true,
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error('Error tracking conversation progress:', error);
    return {
      success: false,
      data: null,
      error: 'Failed to track progress',
    };
  }
}

/**
 * Get conversation progress for a specific exercise
 */
export async function getConversationProgress(exerciseId: number) {
  try {
    const response = await axiosInstance.get<
      ApiResponse<{
        last_step_completed: number;
        is_completed: boolean;
      }>
    >(`/api/exercises/conversation/progress/${exerciseId}`);

    return {
      success: true,
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error('Error getting conversation progress:', error);
    return {
      success: false,
      data: {
        last_step_completed: 0,
        is_completed: false,
      },
      error: 'Failed to get progress',
    };
  }
}

/**
 * Get all conversation exercises for a specific language
 */
export async function getConversationExercises(languageId: number) {
  try {
    const response = await axiosInstance.get<
      ApiResponse<{
        exercises: Array<{
          id: number;
          title: string;
          description: string;
          is_completed: boolean;
          progress_percentage: number;
        }>;
      }>
    >(`/api/exercises/conversation/language/${languageId}`);

    return {
      success: true,
      data: response.data.data.exercises,
      error: null,
    };
  } catch (error) {
    console.error('Error getting conversation exercises:', error);
    return {
      success: false,
      data: [],
      error: 'Failed to get conversation exercises',
    };
  }
}
