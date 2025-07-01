"use client";

import { useState, useEffect, useRef } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Mic, Square, Play, Volume2, CheckCircle, Loader2 } from "lucide-react";
import { WordData } from "@/types/tenant/guidebook";
import ClickableText from "./ClickableText";
import { audioService } from "@/lib/services/audioService";
import { toast } from "sonner";
import { cn } from "@/lib/utils";

interface SpeakingExerciseProps {
  exercise: {
    id: number;
    content: {
      prompt: string;
      text_to_speak: string;
      correct_pronunciation?: string;
      language: string;
      example_audio_url: string;
      word_ids?: number[];
      word_mapping?: Record<string, number>;
      duration?: number;
    };
  };
  onAnswer: (isCorrect: boolean) => void;
  onNext: () => void;
  wordData: Record<string, WordData>;
}

interface FeedbackResult {
  score: number;
  phonemes: {
    text: string;
    score: number;
    isCorrect: boolean;
  }[];
  message: string;
}

export default function SpeakingExercise({
  exercise,
  onAnswer,
  onNext,
  wordData = {},
}: SpeakingExerciseProps) {
  const [isRecording, setIsRecording] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [recordedBlob, setRecordedBlob] = useState<Blob | null>(null);
  const [recordingTime, setRecordingTime] = useState(0);
  const [feedback, setFeedback] = useState<FeedbackResult | null>(null);
  const timerRef = useRef<NodeJS.Timeout | null>(null);
  const maxRecordingTime = exercise.content.duration || 10; // Default 10 seconds

  // Reset state when exercise changes
  useEffect(() => {
    setIsRecording(false);
    setIsPlaying(false);
    setIsSubmitted(false);
    setIsProcessing(false);
    setRecordedBlob(null);
    setRecordingTime(0);
    setFeedback(null);

    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current);
      }
    };
  }, [exercise]);

  const startRecording = async () => {
    try {
      await audioService.startRecording();
      setIsRecording(true);
      setRecordingTime(0);

      // Start timer
      timerRef.current = setInterval(() => {
        setRecordingTime((prev) => {
          if (prev >= maxRecordingTime) {
            stopRecording();
            return maxRecordingTime;
          }
          return prev + 0.1;
        });
      }, 100);

      toast.message("Recording started");
    } catch (_error) {
      toast.error("Could not access microphone");
    }
  };

  const stopRecording = async () => {
    if (isRecording) {
      if (timerRef.current) {
        clearInterval(timerRef.current);
      }

      try {
        const blob = await audioService.stopRecording();
        setRecordedBlob(blob);
        setIsRecording(false);
        toast.message("Recording stopped");
      } catch (_error) {
        toast.error("Error stopping recording");
        setIsRecording(false);
      }
    }
  };

  const playAudio = async (type: "example" | "recorded" = "example") => {
    try {
      setIsPlaying(true);

      if (type === "example") {
        const audio = new Audio(exercise.content.example_audio_url);
        await audio.play();
        audio.onended = () => setIsPlaying(false);
      } else if (recordedBlob) {
        const audio = new Audio(URL.createObjectURL(recordedBlob));
        await audio.play();
        audio.onended = () => setIsPlaying(false);
      }
    } catch (_error) {
      toast.error("Error playing audio");
      setIsPlaying(false);
    }
  };

  const submitRecording = async () => {
    if (!recordedBlob) {
      toast.error("Please record your answer first");
      return;
    }

    setIsProcessing(true);

    try {
      // Create form data to send the audio file
      const formData = new FormData();
      formData.append("audio", recordedBlob, "recording.webm");
      formData.append("exercise_id", exercise.id.toString());
      formData.append("language", exercise.content.language);
      formData.append("text_to_speak", exercise.content.text_to_speak);

      // Send to backend for processing
      const response = await fetch("/api/v1/exercises/speaking/check", {
        method: "POST",
        body: formData,
      });

      if (!response.ok) {
        throw new Error("Failed to process recording");
      }

      const result = await response.json();
      setFeedback(result.data);
      setIsSubmitted(true);

      // Call onAnswer with the result
      onAnswer(result.data.score >= 0.7); // Consider 70% or higher as correct
    } catch (error) {
      toast.error("Error processing your recording");
      console.error(error);
    } finally {
      setIsProcessing(false);
    }
  };

  const renderWithClickableWords = (text: string) => {
    return <ClickableText text={text} wordData={wordData} />;
  };

  return (
    <Card className="w-full">
      <CardContent className="p-6">
        <div className="space-y-6">
          {/* Prompt and Text to Speak */}
          <div className="space-y-4">
            <div className="text-center">
              <h3 className="text-lg text-muted-foreground">
                {renderWithClickableWords(
                  exercise.content.prompt || "Speak this phrase"
                )}
              </h3>
              <div className="mt-4 text-2xl font-medium">
                {renderWithClickableWords(exercise.content.text_to_speak)}
              </div>
              {exercise.content.correct_pronunciation && (
                <p className="mt-2 font-mono text-sm text-muted-foreground">
                  /{exercise.content.correct_pronunciation}/
                </p>
              )}
            </div>
          </div>

          {/* Example Audio */}
          <div className="flex justify-center">
            <Button
              variant="outline"
              size="lg"
              className="w-40"
              onClick={() => playAudio("example")}
              disabled={isPlaying || isRecording}
            >
              {isPlaying ? (
                <Loader2 className="w-5 h-5 mr-2 animate-spin" />
              ) : (
                <Volume2 className="w-5 h-5 mr-2" />
              )}
              Listen
            </Button>
          </div>

          {/* Recording Controls */}
          <div className="space-y-4">
            {isRecording ? (
              <div className="space-y-4">
                <div className="flex justify-center">
                  <Progress
                    value={(recordingTime / maxRecordingTime) * 100}
                    className="w-full h-2"
                  />
                </div>
                <div className="flex justify-center">
                  <Button
                    variant="destructive"
                    size="lg"
                    onClick={stopRecording}
                    className="w-40 animate-pulse"
                  >
                    <Square className="w-5 h-5 mr-2" />
                    Stop
                  </Button>
                </div>
                <p className="text-sm text-center text-muted-foreground">
                  Recording: {recordingTime.toFixed(1)}s / {maxRecordingTime}s
                </p>
              </div>
            ) : (
              <div className="flex justify-center">
                <Button
                  variant="outline"
                  size="lg"
                  className="w-40 text-red-600 bg-red-50 hover:bg-red-100"
                  onClick={startRecording}
                  disabled={isPlaying || isSubmitted}
                >
                  <Mic className="w-5 h-5 mr-2" />
                  Record
                </Button>
              </div>
            )}
          </div>

          {/* Recorded Audio Playback */}
          {recordedBlob && !isRecording && !isSubmitted && (
            <div className="space-y-4">
              <div className="flex justify-center gap-4">
                <Button
                  variant="outline"
                  onClick={() => playAudio("recorded")}
                  disabled={isPlaying}
                >
                  <Play className="w-5 h-5 mr-2" />
                  Play Recording
                </Button>
                <Button
                  variant="default"
                  onClick={submitRecording}
                  disabled={isProcessing}
                >
                  {isProcessing ? (
                    <Loader2 className="w-5 h-5 mr-2 animate-spin" />
                  ) : (
                    <CheckCircle className="w-5 h-5 mr-2" />
                  )}
                  Submit
                </Button>
              </div>
            </div>
          )}

          {/* Feedback Display */}
          {isSubmitted && feedback && (
            <div className="mt-6 space-y-4">
              <div className="p-4 rounded-lg bg-muted">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-medium">Your Pronunciation</h3>
                  <div className="flex items-center">
                    <span className="mr-2">Score:</span>
                    <span
                      className={cn(
                        "font-bold",
                        feedback.score >= 0.7
                          ? "text-green-600"
                          : "text-red-600"
                      )}
                    >
                      {Math.round(feedback.score * 100)}%
                    </span>
                  </div>
                </div>

                <div className="space-y-2">
                  {feedback.phonemes.map((phoneme, index) => (
                    <span
                      key={index}
                      className={cn(
                        "inline-block px-1 py-0.5 rounded mr-1",
                        phoneme.isCorrect
                          ? "bg-green-100 text-green-800"
                          : "bg-red-100 text-red-800"
                      )}
                    >
                      {phoneme.text}
                    </span>
                  ))}
                </div>

                <p className="mt-4 text-sm">{feedback.message}</p>
              </div>

              <div className="flex justify-center">
                <Button onClick={onNext}>Continue</Button>
              </div>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
