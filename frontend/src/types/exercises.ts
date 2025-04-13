/**
 * Types of exercises supported by the system
 */
export type ExerciseType =
  | "multiple_choice"
  | "fill_blank"
  | "matching"
  | "writing"
  | "speaking"
  | "conversation"
  | "listening"
  | "picture";

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
 * Conversation question
 */
export interface ConversationQuestion extends BaseQuestion {
  type: "conversation";
  title: string;
  description: string;
  steps: Array<{
    type: "dialogue" | "question" | "choice";
    content: {
      speaker?: string;
      text?: string;
      audio_url?: string;
      translation?: string;
      question?: string;
      hint?: string;
      options?: string[];
    };
  }>;
  language: string;
}

/**
 * Listening question
 */
export interface ListeningQuestion extends BaseQuestion {
  type: "listening";
  audio_url: string;
  transcript: string;
  prompt: string;
  language: string;
  difficulty: "beginner" | "intermediate" | "advanced";
}

/**
 * Picture question
 */
export interface PictureQuestion extends BaseQuestion {
  type: "picture";
  question: string;
  mode: "word_to_image" | "image_to_word";
  images?: Array<{ url: string; alt: string }>;
  words?: string[];
  target_image?: string;
  language: string;
}

/**
 * Union type for all question types
 */
export type Question =
  | MultipleChoiceQuestion
  | FillBlankQuestion
  | MatchingQuestion
  | WritingQuestion
  | SpeakingQuestion
  | ConversationQuestion
  | ListeningQuestion
  | PictureQuestion;

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

  // Conversation properties
  title?: string;
  description?: string;
  steps?: Array<{
    type: "dialogue" | "question" | "choice";
    content: {
      speaker?: string;
      text?: string;
      audio_url?: string;
      translation?: string;
      question?: string;
      hint?: string;
      options?: string[];
    };
  }>;

  // Listening properties
  audio_url?: string;
  transcript?: string;
  listening_prompt?: string;
  difficulty?: "beginner" | "intermediate" | "advanced";

  // Picture properties
  picture_question?: string;
  mode?: "word_to_image" | "image_to_word";
  images?: Array<{ url: string; alt: string }>;
  words?: string[];
  target_image?: string;
}

/**
 * Exercise answers structure
 */
export interface ExerciseAnswers {
  correct?: string | string[] | Record<string, number>; // For most exercise types
  steps?: Record<
    number,
    {
      // For conversation exercises
      correct: string | string[] | number;
    }
  >;
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
