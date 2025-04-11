'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface UserProfile {
  id: number;
  name: string;
  email: string;
  learning_level: string;
  preferred_language: string;
  daily_goal: number;
  notification_preferences: {
    email: boolean;
    push: boolean;
    study_reminders: boolean;
  };
  created_at: string;
}

interface ApiError {
  message: string;
  status: number;
}

function isAxiosError(error: unknown): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === 'object' && 'isAxiosError' in error;
}

export async function getUserProfile() {
  try {
    const response = await axiosInstance.get<ApiResponse<UserProfile>>('/api/user/profile');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch user profile'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateUserProfile(formData: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<UserProfile>>('/api/user/profile', {
      name: formData.get('name'),
      learning_level: formData.get('learning_level'),
      preferred_language: formData.get('preferred_language'),
      daily_goal: formData.get('daily_goal'),
      notification_preferences: {
        email: formData.get('email_notifications') === 'true',
        push: formData.get('push_notifications') === 'true',
        study_reminders: formData.get('study_reminders') === 'true'
      }
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update user profile'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updatePassword(formData: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<{ success: boolean }>>('/api/user/password', {
      current_password: formData.get('current_password'),
      password: formData.get('new_password'),
      password_confirmation: formData.get('password_confirmation')
    });

    return {
      success: response.data.data.success,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        success: false,
        error: error.response?.data?.message || 'Failed to update password'
      };
    }
    return {
      success: false,
      error: 'An unexpected error occurred'
    };
  }
}

export async function deleteAccount() {
  try {
    await axiosInstance.delete<ApiResponse<void>>('/api/user/account');
    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete account'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}