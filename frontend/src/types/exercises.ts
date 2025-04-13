/**
 * Types of exercises supported by the system
 */
export type ExerciseType =
  | "multiple_choice"
  | "fill_blank"
  | "matching"
  | "writing"
  | "speaking";

/**
 * Base interface for all exercise questions
 */
export interface BaseQuestion {
  id: number;
  type: ExerciseType;
  explanation?: string;
  audioUrl?: string;
  imageUrl?: string;
}

/**
 * Multiple choice question
 */
export interface MultipleChoiceQuestion extends BaseQuestion {
  type: "multiple_choice";
  question: string;
  options: string[];
  correctAnswer: string;
}

/**
 * Fill in the blank question
 */
export interface FillBlankQuestion extends BaseQuestion {
  type: "fill_blank";
  sentence: string;
  blanks: Array<{
    position: number;
    correctAnswer: string;
    alternatives?: string[];
  }>;
}

/**
 * Matching question
 */
export interface MatchingQuestion extends BaseQuestion {
  type: "matching";
  pairs: Array<{
    left: string;
    right: string;
  }>;
}

/**
 * Writing question
 */
export interface WritingQuestion extends BaseQuestion {
  type: "writing";
  prompt: string;
  correctAnswer: string;
  alternatives?: string[];
}

/**
 * Speaking question
 */
export interface SpeakingQuestion extends BaseQuestion {
  type: "speaking";
  textToSpeak: string;
  correctPronunciation: string;
  language: string;
  exampleAudioUrl: string;
}

/**
 * Union type for all question types
 */
export type Question =
  | MultipleChoiceQuestion
  | FillBlankQuestion
  | MatchingQuestion
  | WritingQuestion
  | SpeakingQuestion;

/**
 * Exercise content structure
 */
export interface ExerciseContent {
  // Common properties
  word_ids?: number[];
  word_mapping?: Record<string, number>;

  // Multiple choice properties
  question?: string;
  options?: string[];
  explanation?: string;

  // Fill in blank properties
  text?: string;
  blanks?: number[];

  // Matching properties
  instructions?: string;
  items?: string[];
  matches?: string[];

  // Writing properties
  prompt?: string;
  min_words?: number;
  max_words?: number;

  // Speaking properties
  duration?: number;
}

/**
 * Exercise answers structure
 */
export interface ExerciseAnswers {
  correct: any; // Can be string, string[], or Record<string, number> depending on exercise type
}

/**
 * Exercise data structure
 */
export interface Exercise {
  id: number;
  section_id: number;
  lesson_id: number;
  title: string;
  slug: string;
  type: ExerciseType;
  content: ExerciseContent;
  answers?: ExerciseAnswers;
  order: number;
  status: string;
  review_status?: string;
  created_at?: string;
  updated_at?: string;
}

/**
 * User's answer to a question
 */
export interface QuestionAnswer {
  questionId: number;
  answer: string | string[] | Record<string, string>;
  isCorrect?: boolean;
  timeTaken?: number;
}

/**
 * Exercise attempt result
 */
export interface ExerciseResult {
  exerciseId: number;
  score: number;
  totalQuestions: number;
  correctAnswers: number;
  timeTaken: number;
  answers: QuestionAnswer[];
  completed: boolean;
  passed: boolean;
}
