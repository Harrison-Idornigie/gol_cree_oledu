/**
 * Represents a vocabulary item with its associated data
 */
export interface VocabularyItem {
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
 * Simplified vocabulary word data for use in components
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
 * Response from the vocabulary API
 */
export interface VocabularyResponse {
  id: number;
  word: string;
  translation: string;
  phonetic?: string;
  part_of_speech?: string;
  example?: string;
  pronunciation_url?: string;
  illustration_url?: string;
  difficulty_level?: number;
  similar_words?: VocabularyItem[];
}

/**
 * Vocabulary progress statistics
 */
export interface VocabularyStats {
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
  recent_vocabulary: Array<{
    id: number;
    word: string;
    translation: string;
    mastery: number;
    example?: string;
  }>;
}
