import React, { useEffect, useState, useRef } from 'react';
import { audioService, type WordTiming, type WaveformData } from '@/lib/services/audioService';
import { Button } from '@/components/ui/button';

interface Word {
  id: number;
  text: string;
}

interface AudioTimingEditorProps {
  audioUrl: string;
  words: Word[];
  onTimingsUpdate: (timings: WordTiming[]) => void;
  initialTimings?: WordTiming[];
}

export function AudioTimingEditor({
  audioUrl,
  words,
  onTimingsUpdate,
  initialTimings
}: AudioTimingEditorProps) {
  const [waveform, setWaveform] = useState<WaveformData | null>(null);
  const [timings, setTimings] = useState<WordTiming[]>(initialTimings || []);
  const [activeWordId, setActiveWordId] = useState<number | null>(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const animationFrameRef = useRef<number | null>(null);
  const startTimeRef = useRef<number>(0);

  useEffect(() => {
    loadAudio();
    return () => {
      audioService.dispose();
      stopAnimation();
    };
  }, [audioUrl]);

  useEffect(() => {
    drawWaveform();
  }, [waveform, timings, currentTime]);

  const stopAnimation = () => {
    if (animationFrameRef.current !== null) {
      cancelAnimationFrame(animationFrameRef.current);
      animationFrameRef.current = null;
    }
  };

  const loadAudio = async () => {
    try {
      const data = await audioService.loadAudio(audioUrl);
      setWaveform(data);
    } catch (error) {
      console.error('Failed to load audio:', error);
    }
  };

  const drawWaveform = () => {
    if (!canvasRef.current || !waveform) return;

    const canvas = canvasRef.current;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const { width, height } = canvas;
    ctx.clearRect(0, 0, width, height);

    // Draw waveform
    const points = waveform.points;
    const step = width / points.length;
    const scale = height / Math.max(...points);

    ctx.beginPath();
    ctx.strokeStyle = '#2563eb';
    ctx.lineWidth = 2;

    points.forEach((point, i) => {
      const x = i * step;
      const y = height - (point * scale);
      if (i === 0) {
        ctx.moveTo(x, y);
      } else {
        ctx.lineTo(x, y);
      }
    });
    ctx.stroke();

    // Draw word timings
    timings.forEach(timing => {
      const startX = (timing.start_time / waveform.duration) * width;
      const endX = (timing.end_time / waveform.duration) * width;

      ctx.fillStyle = timing.word_id === activeWordId 
        ? 'rgba(59, 130, 246, 0.3)' 
        : 'rgba(209, 213, 219, 0.2)';
      ctx.fillRect(startX, 0, endX - startX, height);

      // Add timing labels
      ctx.fillStyle = '#4b5563';
      ctx.font = '10px sans-serif';
      ctx.fillText(timing.start_time.toFixed(2), startX, height - 4);
      ctx.fillText(timing.end_time.toFixed(2), endX - 24, height - 4);
    });

    // Draw playhead
    const playheadX = (currentTime / waveform.duration) * width;
    ctx.beginPath();
    ctx.strokeStyle = '#ef4444';
    ctx.lineWidth = 2;
    ctx.moveTo(playheadX, 0);
    ctx.lineTo(playheadX, height);
    ctx.stroke();
  };

  const handleCanvasClick = (e: React.MouseEvent<HTMLCanvasElement>) => {
    if (!waveform || !containerRef.current) return;

    const rect = containerRef.current.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const time = (x / rect.width) * waveform.duration;

    if (activeWordId) {
      // Update timing for active word
      setTimings(prev => {
        const newTimings = [...prev];
        const index = newTimings.findIndex(t => t.word_id === activeWordId);
        
        if (index === -1) {
          newTimings.push({
            word_id: activeWordId,
            start_time: time,
            end_time: time + 0.5 // Default duration
          });
        } else {
          newTimings[index] = {
            ...newTimings[index],
            end_time: Math.max(newTimings[index].start_time + 0.1, time)
          };
          newTimings.sort((a, b) => a.start_time - b.start_time);
        }

        onTimingsUpdate(newTimings);
        return newTimings;
      });
      setActiveWordId(null);
    }
  };

  const handleWordClick = (wordId: number) => {
    setActiveWordId(prev => prev === wordId ? null : wordId);
  };

  const handlePlay = async () => {
    stopAnimation();
    setIsPlaying(true);
    startTimeRef.current = performance.now();
    
    const updatePlayhead = () => {
      const elapsed = (performance.now() - startTimeRef.current) / 1000;
      if (waveform && elapsed <= waveform.duration) {
        setCurrentTime(elapsed);
        animationFrameRef.current = requestAnimationFrame(updatePlayhead);
      } else {
        setIsPlaying(false);
        setCurrentTime(0);
        stopAnimation();
      }
    };
    
    await audioService.playAudioWithTimings(
      audioUrl,
      timings,
      (wordId) => setActiveWordId(wordId),
      () => setActiveWordId(null)
    );
    
    animationFrameRef.current = requestAnimationFrame(updatePlayhead);
  };

  return (
    <div className="space-y-4">
      <div className="flex space-x-4">
        <Button
          onClick={handlePlay}
          disabled={isPlaying}
          variant={isPlaying ? 'secondary' : 'default'}
        >
          {isPlaying ? 'Playing...' : 'Play'}
        </Button>
        <Button
          onClick={() => {
            setTimings([]);
            onTimingsUpdate([]);
          }}
          variant="destructive"
        >
          Reset Timings
        </Button>
      </div>

      <div ref={containerRef} className="relative border rounded-lg p-4 bg-white shadow-sm">
        <canvas
          ref={canvasRef}
          width={800}
          height={200}
          onClick={handleCanvasClick}
          className="w-full cursor-crosshair"
        />
      </div>

      <div className="flex flex-wrap gap-2">
        {words.map(word => (
          <button
            key={word.id}
            onClick={() => handleWordClick(word.id)}
            className={`
              px-3 py-1 rounded-full text-sm transition-colors
              ${activeWordId === word.id
                ? 'bg-blue-500 text-white'
                : timings.some(t => t.word_id === word.id)
                ? 'bg-green-100 text-green-800'
                : 'bg-gray-100 text-gray-800 hover:bg-gray-200'
              }
            `}
          >
            {word.text}
          </button>
        ))}
      </div>

      <div className="text-sm text-gray-500 border-t pt-4">
        <p>Instructions:</p>
        <ol className="list-decimal list-inside space-y-1">
          <li>Click a word to select it</li>
          <li>Click on the waveform to set its start time</li>
          <li>Click again to set its end time</li>
          <li>Times are shown in seconds below each marker</li>
        </ol>
      </div>
    </div>
  );
}