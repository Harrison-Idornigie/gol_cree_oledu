import { axiosInstance, setServerAuthToken, isAxiosError } from '@/lib/axios';
import { ApiResponse } from '@/types/api';

export interface WordMapping {
  word_id: number;
  position: number;
  text: string;
  clean_text: string;
  existing_word: any;
  auto_created?: boolean;
}

export interface SentenceAnalysis {
  mapped_words: WordMapping[];
  missing_words: Array<{
    text: string;
    clean_text: string;
    position: number;
    suggested_part_of_speech: string;
    suggested_pronunciation: string;
  }>;
  suggested_positions: Array<{
    position: number;
    start_time: number;
    end_time: number;
  }>;
  total_words: number;
  mapping_percentage: number;
}

export interface AnalysisSuggestions {
  auto_create_missing: boolean;
  mapping_quality: 'excellent' | 'good' | 'fair' | 'poor';
  recommended_action: 'ready_to_create' | 'auto_create_recommended' | 'manual_review_recommended' | 'create_words_first';
}

/**
 * Analyze sentence text and get word mapping suggestions
 */
export async function analyzeSentenceText(data: {
  text: string;
  language_id: number;
}) {
  try {
    const config = {
      url: '/team/sentences/analyze-text',
      headers: {}
    };
    await setServerAuthToken(config);
    
    const response = await axiosInstance.post<ApiResponse<{
      analysis: SentenceAnalysis;
      suggestions: AnalysisSuggestions;
    }>>(config.url, data, {
      headers: config.headers
    });
    
    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to analyze sentence'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

/**
 * Create sentence with automatic word mapping
 */
export async function createSentenceWithMapping(data: {
  text: string;
  language_id: number;
  auto_create_words: boolean;
  missing_word_data?: Record<string, {
    pronunciation_key?: string;
    part_of_speech?: string;
    difficulty?: string;
    tags?: string[];
  }>;
  difficulty?: string;
  metadata?: any;
}) {
  try {
    const config = {
      url: '/team/sentences/create-with-mapping',
      headers: {}
    };
    await setServerAuthToken(config);
    
    const response = await axiosInstance.post<ApiResponse<{
      sentence: any;
      mapping_result: {
        mapped_words: WordMapping[];
        created_words?: any[];
        suggested_positions: any[];
        total_words: number;
      };
      created_words: any[];
    }>>(config.url, data, {
      headers: config.headers
    });
    
    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to create sentence with mapping'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}
