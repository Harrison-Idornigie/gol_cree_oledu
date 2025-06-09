'use client';

import React from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { QuestionType } from './types';

interface QuestionEmptyStateProps {
  onTypeSelect: (type: QuestionType) => void;
}

type QuestionTypeOption = {
  type: QuestionType;
  label: string;
  description: string;
  icon: React.ReactNode;
};

const questionTypes: QuestionTypeOption[] = [
  {
    type: 'multiple-choice',
    label: 'Multiple Choice',
    description: 'Choose from several options',
    icon: (
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
        <circle cx="12" cy="12" r="10" />
        <path d="M12 8v8" />
        <path d="M8 12h8" />
      </svg>
    ),
  },
  {
    type: 'translation',
    label: 'Translation',
    description: 'Translate text between languages',
    icon: (
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
        <path d="M2 5h8M6 5v8M10 5l-4 8M14 13h8M18 13v8M22 13l-4 8" />
      </svg>
    ),
  },
  {
    type: 'fill-in-blank',
    label: 'Fill in the Blanks',
    description: 'Complete sentences with missing words',
    icon: (
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
        <path d="M4 7h16M4 12h16M4 17h10" />
      </svg>
    ),
  },
  {
    type: 'matching',
    label: 'Matching',
    description: 'Match pairs of related items',
    icon: (
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
        <path d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    ),
  },
  {
    type: 'listen-type',
    label: 'Listen and Type',
    description: 'Type what you hear',
    icon: (
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
        <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
        <path d="M12 19v4M8 23h8" />
      </svg>
    ),
  },
  {
    type: 'speak-record',
    label: 'Speak and Record',
    description: 'Record your pronunciation',
    icon: (
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
        <circle cx="12" cy="12" r="10" />
        <circle cx="12" cy="12" r="6" />
        <circle cx="12" cy="12" r="2" />
      </svg>
    ),
  },
  {
    type: 'true-false',
    label: 'True or False',
    description: 'Evaluate statements as true or false',
    icon: (
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-6 h-6">
        <path d="M20 6L9 17l-5-5" />
      </svg>
    ),
  },
];

export default function QuestionEmptyState({ onTypeSelect }: QuestionEmptyStateProps) {
  return (
    <Card className="p-6">
      <div className="text-center mb-6">
        <h3 className="text-lg font-semibold">Choose Question Type</h3>
        <p className="text-muted-foreground">
          Select the type of question you want to create
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {questionTypes.map((type) => (
          <Button
            key={type.type}
            variant="outline"
            className="h-auto p-4 flex flex-col items-center gap-2 text-left"
            onClick={() => onTypeSelect(type.type)}
          >
            <div className="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary">
              {type.icon}
            </div>
            <div>
              <h4 className="font-medium">{type.label}</h4>
              <p className="text-sm text-muted-foreground">
                {type.description}
              </p>
            </div>
          </Button>
        ))}
      </div>
    </Card>
  );
}