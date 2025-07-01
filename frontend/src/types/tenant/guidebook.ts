/**
 * Represents a guidebook item with its associated data
 */
export interface GuidebookItem {
  id: number;
  word: string;
  translation: string;
  phonetic?: string;
  part_of_speech?: string;
  example?: string;
  difficulty_level?: number;
  pronunciation_url?: string;
  illustration_url?: string;
  lesson_id?: number;
  language_id?: number;
}

/**
 * Simplified guidebook word data for use in components
 */
export interface WordData {
  id?: number;
  text: string;
  translation?: string;
  phonetic?: string;
  audioUrl?: string;
  partOfSpeech?: string;
  example?: string;
}

/**
 * Dictionary of words indexed by their text
 */
export type WordDictionary = Record<string, WordData>;

/**
 * Response from the guidebook API
 */
export interface GuidebookResponse {
  id: number;
  word: string;
  translation: string;
  phonetic?: string;
  part_of_speech?: string;
  example?: string;
  pronunciation_url?: string;
  illustration_url?: string;
  difficulty_level?: number;
  similar_words?: GuidebookItem[];
}

/**
 * Guidebook progress statistics
 */
export interface GuidebookStats {
  total_words_learned: number;
  words_in_progress: number;
  mastery_levels: {
    beginner: number;
    intermediate: number;
    advanced: number;
  };
  daily_progress: Array<{
    date: string;
    count: number;
  }>;
  recent_guidebook: Array<{
    id: number;
    word: string;
    translation: string;
    mastery: number;
    example?: string;
  }>;
}
