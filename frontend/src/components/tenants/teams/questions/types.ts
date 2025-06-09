export type QuestionType =
  | 'multiple-choice'
  | 'fill-in-blank'
  | 'translation'
  | 'matching'
  | 'true-false'
  | 'listen-type'
  | 'speak-record';

export interface Media {
  id?: number;
  type: 'image' | 'audio';
  url: string;
  alt?: string;
}

export interface BaseQuestion {
  id?: number;
  type: QuestionType;
  order: number;
  explanation?: string;
  difficulty_level?: string;
  media?: Media[];
}

export interface MultipleChoiceQuestion extends BaseQuestion {
  type: 'multiple-choice';
  question: string;
  correct_answer: string;
  options: string[];
  media?: Media[];
}

export interface FillInBlankQuestion extends BaseQuestion {
  type: 'fill-in-blank';
  sentence: string;
  blanks: Array<{
    position: number;
    correct_answer: string;
    alternatives?: string[];
  }>;
  media?: Media[];
}

export interface TranslationQuestion extends BaseQuestion {
  type: 'translation';
  text: string;
  correct_translation: string;
  alternatives?: string[];
  source_language: string;
  target_language: string;
  media?: Media[];
}

export interface MatchingQuestion extends BaseQuestion {
  type: 'matching';
  pairs: Array<{
    left: string;
    right: string;
    media?: Media; // Optional media for each pair
  }>;
}

export interface TrueFalseQuestion extends BaseQuestion {
  type: 'true-false';
  statement: string;
  is_true: boolean;
  media?: Media[];
}

export interface ListenTypeQuestion extends BaseQuestion {
  type: 'listen-type';
  audio: Media; // Required audio file
  correct_text: string;
  alternatives?: string[];
  language: string;
  slow_audio?: Media; // Optional slowed down version
}

export interface SpeakRecordQuestion extends BaseQuestion {
  type: 'speak-record';
  text_to_speak: string;
  correct_pronunciation: string; // IPA or other phonetic representation
  language: string;
  example_audio: Media;
  media?: Media[]; // Additional visual aids
}

export type Question =
  | MultipleChoiceQuestion
  | FillInBlankQuestion
  | TranslationQuestion
  | MatchingQuestion
  | TrueFalseQuestion
  | ListenTypeQuestion
  | SpeakRecordQuestion;

export interface MediaUploadResponse {
  id: number;
  url: string;
  type: 'image' | 'audio';
  alt?: string;
}