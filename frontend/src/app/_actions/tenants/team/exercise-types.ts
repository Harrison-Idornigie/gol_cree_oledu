// Base content types
export interface MultipleChoiceContent {
    question: string;
    options: string[];
}

export interface TranslationContent {
    sourceText: string;
    targetLanguage: string;
}

export interface WordBankContent {
    sentence: string;
    words: string[];
}

export interface FillInBlankContent {
    text: string;
    blanks: {
        position: number;
        length: number;
    }[];
}

export interface MatchPairsContent {
    pairs: Array<{
        source: string;
        target: string;
    }>;
}

export interface SpeakingContent {
    text: string;
    audioUrl?: string;
    acceptedVariations?: string[];
}

export interface ListeningContent {
    audioUrl: string;
    transcript: string;
}

// Answer types
export interface MultipleChoiceAnswer {
    correctOptionIndex: number;
}

export interface TranslationAnswer {
    acceptedTranslations: string[];
}

export interface WordBankAnswer {
    correctOrder: number[];
}

export interface FillInBlankAnswer {
    answers: string[];
}

export interface MatchPairsAnswer {
    correctPairs: Array<{
        sourceIndex: number;
        targetIndex: number;
    }>;
}

export interface SpeakingAnswer {
    text: string;
    minConfidence: number;
}

export interface ListeningAnswer {
    text: string;
    allowedErrors: number;
}

// Distractor types
export interface MultipleChoiceDistractors {
    explanations: Record<number, string>;
}

export interface WordBankDistractors {
    extraWords: string[];
}

export interface MatchPairsDistractors {
    extraTargets: string[];
}

// Combined types for use in exercise actions
export type ExerciseContent =
    | { type: 'multiple_choice'; data: MultipleChoiceContent }
    | { type: 'translation'; data: TranslationContent }
    | { type: 'word_bank'; data: WordBankContent }
    | { type: 'fill_in_blank'; data: FillInBlankContent }
    | { type: 'match_pairs'; data: MatchPairsContent }
    | { type: 'speak'; data: SpeakingContent }
    | { type: 'listen_type'; data: ListeningContent };

export type ExerciseAnswer =
    | { type: 'multiple_choice'; data: MultipleChoiceAnswer }
    | { type: 'translation'; data: TranslationAnswer }
    | { type: 'word_bank'; data: WordBankAnswer }
    | { type: 'fill_in_blank'; data: FillInBlankAnswer }
    | { type: 'match_pairs'; data: MatchPairsAnswer }
    | { type: 'speak'; data: SpeakingAnswer }
    | { type: 'listen_type'; data: ListeningAnswer };

export type ExerciseDistractors =
    | { type: 'multiple_choice'; data: MultipleChoiceDistractors }
    | { type: 'word_bank'; data: WordBankDistractors }
    | { type: 'match_pairs'; data: MatchPairsDistractors }
    | { type: never; data: never };