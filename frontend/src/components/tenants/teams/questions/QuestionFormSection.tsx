'use client';

import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Question, QuestionType } from './types';
import QuestionWrapper from './QuestionWrapper';
import QuestionEmptyState from './QuestionEmptyState';

interface QuestionFormSectionProps {
  questions: Question[];
  onQuestionsChange: (questions: Question[]) => void;
}

const getEmptyQuestion = (type: QuestionType): Question => {
  const baseQuestion = {
    type,
    order: 0,
    explanation: '',
    difficulty_level: 'normal',
  };

  switch (type) {
    case 'multiple-choice':
      return {
        ...baseQuestion,
        type,
        question: '',
        correct_answer: '',
        options: ['', '', '', ''],
      };
    case 'translation':
      return {
        ...baseQuestion,
        type,
        text: '',
        correct_translation: '',
        alternatives: [],
        source_language: 'en',
        target_language: 'es',
      };
    case 'fill-in-blank':
      return {
        ...baseQuestion,
        type,
        sentence: '',
        blanks: [{
          position: 0,
          correct_answer: '',
          alternatives: [],
        }],
      };
    case 'matching':
      return {
        ...baseQuestion,
        type,
        pairs: [
          { left: '', right: '' },
          { left: '', right: '' },
        ],
      };
    case 'listen-type':
      return {
        ...baseQuestion,
        type,
        audio: {
          type: 'audio',
          url: '',
        },
        correct_text: '',
        alternatives: [],
        language: 'en',
      };
    case 'speak-record':
      return {
        ...baseQuestion,
        type,
        text_to_speak: '',
        correct_pronunciation: '',
        language: 'en',
        example_audio: {
          type: 'audio',
          url: '',
        },
      };
    case 'true-false':
      return {
        ...baseQuestion,
        type,
        statement: '',
        is_true: false,
      };
  }
};

export default function QuestionFormSection({
  questions,
  onQuestionsChange,
}: QuestionFormSectionProps) {
  const [showTypeSelector, setShowTypeSelector] = useState(false);

  const handleAddQuestion = (type: QuestionType) => {
    const newQuestion = getEmptyQuestion(type);
    newQuestion.order = questions.length;
    onQuestionsChange([...questions, newQuestion]);
    setShowTypeSelector(false);
  };

  const handleUpdateQuestion = (index: number, updatedQuestion: Question) => {
    onQuestionsChange(
      questions.map((q, i) => (i === index ? updatedQuestion : q))
    );
  };

  const handleRemoveQuestion = (index: number) => {
    onQuestionsChange(
      questions.filter((_, i) => i !== index).map((q, i) => ({ ...q, order: i }))
    );
  };

  return (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h3 className="text-lg font-semibold">Questions</h3>
        <Button
          type="button"
          onClick={() => setShowTypeSelector(true)}
        >
          Add Question
        </Button>
      </div>

      {showTypeSelector && (
        <QuestionEmptyState 
          onTypeSelect={type => handleAddQuestion(type)}
        />
      )}

      {questions.map((question, index) => (
        <div key={index} className="space-y-2">
          <div className="flex items-center gap-2 mb-2">
            <h4 className="font-medium">Question {index + 1}</h4>
          </div>
          <QuestionWrapper
            question={question}
            isEditing={true}
            onUpdate={(updatedQuestion) => handleUpdateQuestion(index, updatedQuestion)}
            onDelete={() => handleRemoveQuestion(index)}
          />
        </div>
      ))}
    </div>
  );
}