export interface Language {
  id: number;
  code: string;
  name: string;
  native_name: string;
  is_active: boolean;
  description?: string;
  learning_paths_count?: number;
  is_popular?: boolean;
}

export interface LanguagePair {
  id: number;
  source_language_id: number;
  target_language_id: number;
  is_active: boolean;
  source_language?: Language;
  target_language?: Language;
  created_at: string;
  updated_at: string;
}

export interface LearningPath {
  id: number;
  title: string;
  name?: string; // For compatibility with existing components
  description: string;
  language_id?: number; // Legacy support
  language_pair_id?: number;
  language?: string; // Legacy support
  language_pair?: LanguagePair;
  source_language?: Language;
  target_language?: Language;
  target_level: string;
  status: string;
  units_count: number;
  lessons_count: number;
  units?: number; // For compatibility with existing components
  unitsCompleted?: number; // For compatibility with existing components
  unlocked?: boolean; // For compatibility with existing components
}

export interface UserProgress {
  status: string;
  meta_data: any;
  completed_at: string | null;
}

export type ApiResponse<T> = {
  data: T;
  message?: string;
};
