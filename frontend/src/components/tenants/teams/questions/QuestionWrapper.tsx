'use client';

import React from 'react';
import {
  Question,
  QuestionType,
  MultipleChoiceQuestion,
  TranslationQuestion,
  FillInBlankQuestion,
  MatchingQuestion,
  ListenTypeQuestion,
  SpeakRecordQuestion,
  TrueFalseQuestion,
} from './types';

import MultipleChoiceQuestionComponent from './MultipleChoiceQuestion';
import TranslationQuestionComponent from './TranslationQuestion';
import FillInBlankQuestionComponent from './FillInBlankQuestion';
import MatchingQuestionComponent from './MatchingQuestion';
import ListenTypeQuestionComponent from './ListenTypeQuestion';
import SpeakRecordQuestionComponent from './SpeakRecordQuestion';
import TrueFalseQuestionComponent from './TrueFalseQuestion';

interface QuestionWrapperProps {
  question: MultipleChoiceQuestion | TranslationQuestion | FillInBlankQuestion | 
           MatchingQuestion | ListenTypeQuestion | SpeakRecordQuestion | TrueFalseQuestion;
  isEditing: boolean;
  onUpdate?: (question: Question) => void;
  onDelete?: () => void;
}

export const getQuestionLabel = (type: QuestionType): string => {
  const labels: Record<QuestionType, string> = {
    'multiple-choice': 'Multiple Choice',
    'translation': 'Translation',
    'fill-in-blank': 'Fill in the Blanks',
    'matching': 'Matching',
    'listen-type': 'Listen and Type',
    'speak-record': 'Speak and Record',
    'true-false': 'True or False',
  };
  return labels[type];
};

export default function QuestionWrapper({ question, isEditing, onUpdate, onDelete }: QuestionWrapperProps) {
  switch (question.type) {
    case 'multiple-choice':
      return (
        <MultipleChoiceQuestionComponent
          question={question}
          isEditing={isEditing}
          onUpdate={onUpdate}
          onDelete={onDelete}
        />
      );

    case 'translation':
      return (
        <TranslationQuestionComponent
          question={question}
          isEditing={isEditing}
          onUpdate={onUpdate}
          onDelete={onDelete}
        />
      );

    case 'fill-in-blank':
      return (
        <FillInBlankQuestionComponent
          question={question}
          isEditing={isEditing}
          onUpdate={onUpdate}
          onDelete={onDelete}
        />
      );

    case 'matching':
      return (
        <MatchingQuestionComponent
          question={question}
          isEditing={isEditing}
          onUpdate={onUpdate}
          onDelete={onDelete}
        />
      );

    case 'listen-type':
      return (
        <ListenTypeQuestionComponent
          question={question}
          isEditing={isEditing}
          onUpdate={onUpdate}
          onDelete={onDelete}
        />
      );

    case 'speak-record':
      return (
        <SpeakRecordQuestionComponent
          question={question}
          isEditing={isEditing}
          onUpdate={onUpdate}
          onDelete={onDelete}
        />
      );

    case 'true-false':
      return (
        <TrueFalseQuestionComponent
          question={question}
          isEditing={isEditing}
          onUpdate={onUpdate}
          onDelete={onDelete}
        />
      );

    default:
      console.warn(`Unknown question type: ${(question as Question).type}`);
      return null;
  }
}