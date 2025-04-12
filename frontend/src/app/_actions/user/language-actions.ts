'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse, Language } from '@/types/learning-path';
import { revalidatePath } from 'next/cache';

// Get all available languages
export async function getAllLanguages() {
  try {
    const response = await axiosInstance.get<ApiResponse<Language[]>>('/languages?with_learning_paths_count=true');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching all languages:', error);
    return {
      data: null,
      error: 'Failed to fetch languages'
    };
  }
}

// Get user's selected languages
export async function getSelectedLanguages() {
  try {
    const response = await axiosInstance.get<ApiResponse<Language[]>>('/user/selected-languages');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching selected languages:', error);
    return {
      data: null,
      error: 'Failed to fetch selected languages'
    };
  }
}

// Select a language to learn
export async function selectLanguage(languageId: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ success: boolean }>>('/user/selected-languages', {
      language_id: languageId
    });
    
    // Revalidate relevant paths
    revalidatePath('/languages');
    revalidatePath('/learn');
    revalidatePath('/profile');
    
    return {
      success: true,
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error selecting language:', error);
    return {
      success: false,
      data: null,
      error: 'Failed to select language'
    };
  }
}

// Remove a language from selected languages
export async function unselectLanguage(languageId: number) {
  try {
    const response = await axiosInstance.delete<ApiResponse<{ success: boolean }>>(`/user/selected-languages/${languageId}`);
    
    // Revalidate relevant paths
    revalidatePath('/languages');
    revalidatePath('/learn');
    revalidatePath('/profile');
    
    return {
      success: true,
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error unselecting language:', error);
    return {
      success: false,
      data: null,
      error: 'Failed to unselect language'
    };
  }
}

// Get language details
export async function getLanguageDetails(languageId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<Language>>(`/languages/${languageId}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching language details:', error);
    return {
      data: null,
      error: 'Failed to fetch language details'
    };
  }
}
