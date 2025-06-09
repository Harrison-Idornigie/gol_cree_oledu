'use client';

import { useState, useRef } from 'react';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { SpeakRecordQuestion as SpeakRecordQuestionType, Media } from './types';
import MediaUploader from './MediaUploader';
import { toast } from 'sonner';

interface SpeakRecordQuestionProps {
  isEditing: boolean;
  question: SpeakRecordQuestionType;
  onUpdate?: (question: SpeakRecordQuestionType) => void;
  onDelete?: () => void;
}

export default function SpeakRecordQuestion({
  isEditing,
  question,
  onUpdate,
  onDelete,
}: SpeakRecordQuestionProps) {
  const [localQuestion, setLocalQuestion] = useState(question);
  const [isPlaying, setIsPlaying] = useState(false);
  const [isRecording, setIsRecording] = useState(false);
  const [recordedBlob, setRecordedBlob] = useState<Blob | null>(null);
  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const audioChunksRef = useRef<Blob[]>([]);

  const updateQuestion = (updates: Partial<SpeakRecordQuestionType>) => {
    const updated = { ...localQuestion, ...updates };
    setLocalQuestion(updated);
    onUpdate?.(updated);
  };

  const startRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const mediaRecorder = new MediaRecorder(stream);
      mediaRecorderRef.current = mediaRecorder;
      audioChunksRef.current = [];

      mediaRecorder.ondataavailable = (event) => {
        audioChunksRef.current.push(event.data);
      };

      mediaRecorder.onstop = () => {
        const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/wav' });
        setRecordedBlob(audioBlob);
        stream.getTracks().forEach(track => track.stop());
      };

      mediaRecorder.start();
      setIsRecording(true);
      toast.message('Recording started');
    } catch {
      toast.error('Could not access microphone');
    }
  };

  const stopRecording = () => {
    if (mediaRecorderRef.current && isRecording) {
      mediaRecorderRef.current.stop();
      setIsRecording(false);
      toast.message('Recording stopped');
    }
  };

  const playAudio = async (type: 'example' | 'recorded' = 'example') => {
    try {
      setIsPlaying(true);
      const audio = new Audio(
        type === 'example' 
          ? localQuestion.example_audio.url 
          : URL.createObjectURL(recordedBlob!)
      );
      await audio.play();
      audio.onended = () => setIsPlaying(false);
    } catch {
      toast.error('Error playing audio');
      setIsPlaying(false);
    }
  };

  if (isEditing) {
    return (
      <Card className="p-6 space-y-4">
        <div className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium">Text to Speak</label>
            <Input
              value={localQuestion.text_to_speak}
              onChange={(e) =>
                updateQuestion({ text_to_speak: e.target.value })
              }
              placeholder="Enter text for users to pronounce"
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Correct Pronunciation</label>
            <Input
              value={localQuestion.correct_pronunciation}
              onChange={(e) =>
                updateQuestion({ correct_pronunciation: e.target.value })
              }
              placeholder="IPA or phonetic representation"
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Language</label>
            <select
              className="w-full px-3 py-2 rounded-md border border-input bg-background"
              value={localQuestion.language}
              onChange={(e) =>
                updateQuestion({ language: e.target.value })
              }
            >
              <option value="en">English</option>
              <option value="es">Spanish</option>
              <option value="fr">French</option>
              <option value="de">German</option>
              {/* Add more languages as needed */}
            </select>
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Example Audio</label>
            <MediaUploader
              media={[localQuestion.example_audio]}
              onUpdate={(media: Media[]) =>
                updateQuestion({ example_audio: media[0] })
              }
              allowedTypes={['audio']}
              maxFiles={1}
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Additional Media (Optional)</label>
            <MediaUploader
              media={localQuestion.media || []}
              onUpdate={(media: Media[]) =>
                updateQuestion({ media })
              }
              allowedTypes={['image', 'audio']}
              maxFiles={2}
            />
          </div>
        </div>
      </Card>
    );
  }

  return (
    <Card className="p-6">
      <div className="space-y-6">
        {/* Text and Visual Aids */}
        <div className="text-center space-y-2">
          <h3 className="text-2xl font-medium">{localQuestion.text_to_speak}</h3>
          <p className="text-muted-foreground font-mono">
            /{localQuestion.correct_pronunciation}/
          </p>
        </div>

        {/* Media Display */}
        {localQuestion.media?.map((item, index) => (
          <div key={index} className="mb-4">
            {item.type === 'image' ? (
              <img
                src={item.url}
                alt={item.alt || ''}
                className="max-w-full h-auto rounded-lg mx-auto"
              />
            ) : (
              <audio controls className="w-full">
                <source src={item.url} type="audio/mpeg" />
                Your browser does not support the audio element.
              </audio>
            )}
          </div>
        ))}

        {/* Audio Controls */}
        <div className="flex justify-center gap-4">
          <Button
            variant="outline"
            size="lg"
            className="w-32"
            onClick={() => playAudio('example')}
            disabled={isPlaying}
          >
            {isPlaying ? (
              <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary" />
            ) : (
              <>
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="w-6 h-6 mr-2"
                >
                  <polygon points="5 3 19 12 5 21 5 3" />
                </svg>
                Listen
              </>
            )}
          </Button>
        </div>

        {/* Recording Controls */}
        <div className="flex justify-center gap-4">
          {!isRecording ? (
            <Button
              variant="outline"
              onClick={startRecording}
              className="bg-red-50 hover:bg-red-100 text-red-600"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="w-6 h-6 mr-2"
              >
                <circle cx="12" cy="12" r="6" fill="currentColor" />
              </svg>
              Start Recording
            </Button>
          ) : (
            <Button
              variant="outline"
              onClick={stopRecording}
              className="bg-red-100 text-red-600 animate-pulse"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="w-6 h-6 mr-2"
              >
                <rect x="6" y="6" width="12" height="12" />
              </svg>
              Stop Recording
            </Button>
          )}
        </div>

        {/* Recorded Audio Playback */}
        {recordedBlob && (
          <div className="flex justify-center gap-4">
            <Button
              variant="outline"
              onClick={() => playAudio('recorded')}
              disabled={isPlaying}
            >
              Listen to Your Recording
            </Button>
            <Button
              variant="outline"
              className="text-red-600"
              onClick={() => setRecordedBlob(null)}
            >
              Delete Recording
            </Button>
          </div>
        )}

        {/* Actions */}
        {(onUpdate || onDelete) && (
          <div className="flex justify-end gap-2 mt-4">
            {onUpdate && (
              <Button variant="outline" onClick={() => onUpdate(localQuestion)}>
                Edit
              </Button>
            )}
            {onDelete && (
              <Button
                variant="outline"
                className="text-red-600"
                onClick={onDelete}
              >
                Delete
              </Button>
            )}
          </div>
        )}
      </div>
    </Card>
  );
}