"use client";

import { useState, useEffect } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Label } from "@/components/ui/label";
import { CheckCircle, XCircle, Volume2 } from "lucide-react";
import { WordData } from "@/types/tenant/vocabulary";
import ClickableText from "./ClickableText";
import {
  submitConversationAnswer,
  trackConversationProgress,
  getConversationProgress,
} from "@/app/_actions/tenants/student/conversation-actions";

interface ConversationStep {
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
}

interface ConversationExerciseProps {
  exercise: {
    id: number;
    type: "conversation";
    content: {
      title: string;
      description: string;
      steps: ConversationStep[];
      word_mapping?: Record<string, number>;
      language?: string;
    };
    answers?: {
      steps: Record<
        number,
        {
          correct: string | string[] | number;
        }
      >;
    };
  };
  onAnswer: (isCorrect: boolean) => void;
  onNext: () => void;
  wordData: Record<string, WordData>;
}

export default function ConversationExercise({
  exercise,
  onAnswer,
  onNext,
  wordData,
}: ConversationExerciseProps) {
  const [currentStepIndex, setCurrentStepIndex] = useState(0);
  const [userAnswer, setUserAnswer] = useState("");
  const [selectedChoice, setSelectedChoice] = useState<number | null>(null);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [isCorrect, setIsCorrect] = useState(false);
  const [showHint, setShowHint] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [conversationHistory, setConversationHistory] = useState<
    ConversationStep[]
  >([]);

  // Merge wordData with any word_mapping from the exercise
  const localWordData = { ...wordData };

  useEffect(() => {
    // Reset state when moving to a new step
    setUserAnswer("");
    setSelectedChoice(null);
    setIsSubmitted(false);
    setIsCorrect(false);
    setShowHint(false);
  }, [currentStepIndex]);

  useEffect(() => {
    // Add the first step to the conversation history
    if (exercise.content.steps.length > 0 && conversationHistory.length === 0) {
      setConversationHistory([exercise.content.steps[0]]);
    }

    // Load conversation progress
    const loadProgress = async () => {
      const response = await getConversationProgress(exercise.id);
      if (response.success && response.data) {
        const { last_step_completed } = response.data;

        // If there's progress, load the conversation history up to that point
        if (last_step_completed > 0) {
          const history = [];
          for (
            let i = 0;
            i <= last_step_completed && i < exercise.content.steps.length;
            i++
          ) {
            history.push(exercise.content.steps[i]);
          }
          setConversationHistory(history);
          setCurrentStepIndex(
            Math.min(last_step_completed + 1, exercise.content.steps.length - 1)
          );
        }
      }
    };

    loadProgress();
  }, [exercise.content.steps, conversationHistory.length, exercise.id]);

  const currentStep = exercise.content.steps[currentStepIndex];

  const handleSubmit = async () => {
    if (currentStep.type === "dialogue") {
      // Dialogue steps don't have correct/incorrect answers
      handleNext();
      return;
    }

    let answer: string | number = "";

    if (currentStep.type === "question") {
      answer = userAnswer;
    } else if (currentStep.type === "choice") {
      answer = selectedChoice !== null ? selectedChoice : -1;
    }

    // Submit the answer to the server
    const response = await submitConversationAnswer(
      exercise.id,
      currentStepIndex,
      answer
    );

    const correct = response.success && response.data?.is_correct === true;

    setIsCorrect(!!correct);
    setIsSubmitted(true);
    onAnswer(!!correct);
  };

  const handleNext = async () => {
    // Add the current step to the conversation history if it's not already there
    if (!conversationHistory.includes(currentStep)) {
      setConversationHistory([...conversationHistory, currentStep]);
    }

    // Track progress on the server
    await trackConversationProgress(
      exercise.id,
      currentStepIndex,
      currentStepIndex === exercise.content.steps.length - 1
    );

    // If there are more steps, move to the next one
    if (currentStepIndex < exercise.content.steps.length - 1) {
      const nextIndex = currentStepIndex + 1;
      setCurrentStepIndex(nextIndex);

      // Add the next step to the conversation history if it's a dialogue
      if (exercise.content.steps[nextIndex].type === "dialogue") {
        setConversationHistory([
          ...conversationHistory,
          currentStep,
          exercise.content.steps[nextIndex],
        ]);
      }
    } else {
      // If we've reached the end of the conversation, call onNext
      onNext();
    }
  };

  const playAudio = (audioUrl: string) => {
    if (isPlaying) return;

    setIsPlaying(true);

    const audio = new Audio(audioUrl);
    audio.onended = () => setIsPlaying(false);
    audio.play().catch((error) => {
      console.error("Error playing audio:", error);
      setIsPlaying(false);
    });
  };

  const renderDialogue = (step: ConversationStep) => {
    if (!step.content.speaker || !step.content.text) return null;

    return (
      <div className="flex items-start mb-4 space-x-3">
        <div className="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-full bg-primary text-primary-foreground">
          {step.content.speaker.charAt(0)}
        </div>
        <div className="flex-1">
          <div className="font-medium">{step.content.speaker}</div>
          <div className="p-3 mt-1 rounded-lg bg-muted">
            <ClickableText
              text={step.content.text}
              wordData={localWordData}
              wordMapping={exercise.content.word_mapping}
            />
            {step.content.audio_url && (
              <Button
                variant="ghost"
                size="sm"
                className="w-6 h-6 p-0 ml-2"
                onClick={() => playAudio(step.content.audio_url!)}
                disabled={isPlaying}
              >
                <Volume2 className="w-4 h-4" />
              </Button>
            )}
            {step.content.translation && (
              <div className="mt-1 text-xs text-muted-foreground">
                {step.content.translation}
              </div>
            )}
          </div>
        </div>
      </div>
    );
  };

  const renderQuestion = (step: ConversationStep) => {
    if (!step.content.question) return null;

    return (
      <div className="space-y-4">
        <div className="text-lg font-medium">{step.content.question}</div>
        <Input
          value={userAnswer}
          onChange={(e) => setUserAnswer(e.target.value)}
          placeholder="Type your answer..."
          disabled={isSubmitted}
          className="w-full"
        />
        {showHint && step.content.hint && (
          <div className="text-sm text-muted-foreground">
            Hint: {step.content.hint}
          </div>
        )}
        {isSubmitted && (
          <div
            className={`flex items-center ${
              isCorrect ? "text-green-600" : "text-red-600"
            }`}
          >
            {isCorrect ? (
              <>
                <CheckCircle className="w-5 h-5 mr-2" />
                <span>Correct!</span>
              </>
            ) : (
              <>
                <XCircle className="w-5 h-5 mr-2" />
                <span>Not quite right. Try again or continue.</span>
              </>
            )}
          </div>
        )}
      </div>
    );
  };

  const renderChoice = (step: ConversationStep) => {
    if (!step.content.question || !step.content.options) return null;

    return (
      <div className="space-y-4">
        <div className="text-lg font-medium">{step.content.question}</div>
        <RadioGroup
          value={selectedChoice?.toString()}
          onValueChange={(value) => setSelectedChoice(parseInt(value))}
          disabled={isSubmitted}
        >
          {step.content.options.map((option, index) => (
            <div key={index} className="flex items-center space-x-2">
              <RadioGroupItem value={index.toString()} id={`option-${index}`} />
              <Label htmlFor={`option-${index}`} className="flex-1">
                {option}
              </Label>
            </div>
          ))}
        </RadioGroup>
        {isSubmitted && (
          <div
            className={`flex items-center ${
              isCorrect ? "text-green-600" : "text-red-600"
            }`}
          >
            {isCorrect ? (
              <>
                <CheckCircle className="w-5 h-5 mr-2" />
                <span>Correct!</span>
              </>
            ) : (
              <>
                <XCircle className="w-5 h-5 mr-2" />
                <span>Not quite right. Try again or continue.</span>
              </>
            )}
          </div>
        )}
      </div>
    );
  };

  const renderCurrentStep = () => {
    switch (currentStep.type) {
      case "dialogue":
        return renderDialogue(currentStep);
      case "question":
        return renderQuestion(currentStep);
      case "choice":
        return renderChoice(currentStep);
      default:
        return null;
    }
  };

  return (
    <Card className="w-full">
      <CardContent className="p-6">
        <div className="space-y-6">
          {/* Conversation Title */}
          <div className="text-center">
            <h2 className="text-2xl font-bold">{exercise.content.title}</h2>
            <p className="text-muted-foreground">
              {exercise.content.description}
            </p>
          </div>

          {/* Conversation History */}
          <div className="space-y-4 max-h-[300px] overflow-y-auto p-2 border rounded-lg">
            {conversationHistory.map((step, index) => (
              <div key={index}>
                {step.type === "dialogue" && renderDialogue(step)}
              </div>
            ))}
          </div>

          {/* Current Step */}
          <div className="p-4 border rounded-lg bg-accent/10">
            {renderCurrentStep()}
          </div>

          {/* Action Buttons */}
          <div className="flex justify-between">
            {currentStep.type !== "dialogue" && !isSubmitted ? (
              <>
                <Button
                  variant="outline"
                  onClick={() => setShowHint(true)}
                  disabled={!currentStep.content.hint}
                >
                  Hint
                </Button>
                <Button onClick={handleSubmit}>Check</Button>
              </>
            ) : (
              <>
                <div></div> {/* Empty div for spacing */}
                <Button onClick={handleNext}>Continue</Button>
              </>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
