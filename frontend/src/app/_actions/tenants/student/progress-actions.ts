'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface Progress {
  vocabulary: {
    total_words: number;
    learned_words: number;
    current_streak: number;
  };
  grammar: {
    total_exercises: number;
    completed_exercises: number;
    average_score: number;
  };
  reading: {
    total_passages: number;
    completed_passages: number;
    comprehension_rate: number;
  };
  overall: {
    level: string;
    xp_points: number;
    achievements: string[];
  };
}

interface ApiError {
  message: string;
  status: number;
}

function isAxiosError(error: unknown): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === 'object' && 'isAxiosError' in error;
}

export async function getUserProgress() {
  try {
    const response = await axiosInstance.get<ApiResponse<Progress>>('/api/progress');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch user progress'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getProgressByCategory(category: 'vocabulary' | 'grammar' | 'reading') {
  try {
    const response = await axiosInstance.get<ApiResponse<Progress[typeof category]>>(`/api/progress/${category}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch category progress'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getAchievements() {
  try {
    const response = await axiosInstance.get<ApiResponse<string[]>>('/api/progress/achievements');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch achievements'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getStreak() {
  try {
    const response = await axiosInstance.get<ApiResponse<{
      current_streak: number;
      longest_streak: number;
      last_activity_date: string;
    }>>('/api/progress/streak');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch streak information'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}