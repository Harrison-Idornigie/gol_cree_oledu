import  AxiosInstance  from '@/lib/axios';

declare global {
  interface Window {
    webkitAudioContext: typeof AudioContext;
  }
}

export interface WordTiming {
  word_id: number;
  start_time: number;
  end_time: number;
  metadata?: {
    emphasis?: boolean;
    pause_after?: number;
    pronunciation_notes?: string;
  };
}

export interface WaveformData {
  points: number[];
  duration: number;
}

export interface AudioUploadResponse {
  url: string;
  timings: WordTiming[];
}

class AudioService {
  private audioContext: AudioContext | null = null;
  private audioBuffer: AudioBuffer | null = null;
  private mediaRecorder: MediaRecorder | null = null;
  private recordedChunks: Blob[] = [];

  constructor(private api: typeof AxiosInstance) {}
  /**
   * Initialize audio context for web audio operations.
   */
  private async initAudioContext(): Promise<AudioContext> {
    if (!this.audioContext) {
      this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    return this.audioContext;
  }

  /**
   * Load audio file and create waveform data.
   */
  public async loadAudio(url: string): Promise<WaveformData> {
    const context = await this.initAudioContext();
    const response = await this.api.get<ArrayBuffer>(url, { 
      responseType: 'arraybuffer',
      headers: { Accept: 'audio/*' }
    });
    
    this.audioBuffer = await context.decodeAudioData(response.data);

    // Create waveform data
    const channelData = this.audioBuffer.getChannelData(0);
    const samples = 100;
    const blockSize = Math.floor(channelData.length / samples);
    const points = [];

    for (let i = 0; i < samples; i++) {
      const start = blockSize * i;
      let sum = 0;
      for (let j = 0; j < blockSize; j++) {
        sum += Math.abs(channelData[start + j]);
      }
      points.push(sum / blockSize);
    }

    return {
      points,
      duration: this.audioBuffer.duration
    };
  }

  /**
   * Play audio with word highlighting.
   */
  public async playAudioWithTimings(
    url: string,
    timings: WordTiming[],
    onWordStart: (wordId: number) => void,
    onWordEnd: (wordId: number) => void
  ): Promise<void> {
    const context = await this.initAudioContext();
    if (!this.audioBuffer) {
      const response = await this.api.get<ArrayBuffer>(url, { 
        responseType: 'arraybuffer',
        headers: { Accept: 'audio/*' }
      });
      this.audioBuffer = await context.decodeAudioData(response.data);
    }

    const source = context.createBufferSource();
    source.buffer = this.audioBuffer;
    source.connect(context.destination);

    // Schedule word highlight events
    timings.forEach(timing => {
      // Schedule word start
      setTimeout(() => {
        onWordStart(timing.word_id);
      }, timing.start_time * 1000);

      // Schedule word end
      setTimeout(() => {
        onWordEnd(timing.word_id);
      }, timing.end_time * 1000);
    });

    source.start();
  }

  /**
   * Start recording audio.
   */
  public async startRecording(): Promise<void> {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    this.recordedChunks = [];
    this.mediaRecorder = new MediaRecorder(stream, {
      mimeType: 'audio/webm;codecs=opus'
    });

    this.mediaRecorder.ondataavailable = (event) => {
      if (event.data.size > 0) {
        this.recordedChunks.push(event.data);
      }
    };

    this.mediaRecorder.start();
  }

  /**
   * Stop recording and get the audio blob.
   */
  public async stopRecording(): Promise<Blob> {
    return new Promise((resolve) => {
      if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
        this.mediaRecorder.onstop = () => {
          const blob = new Blob(this.recordedChunks, { 
            type: 'audio/webm;codecs=opus'
          });
          resolve(blob);
        };
        this.mediaRecorder.stop();
      } else {
        resolve(new Blob([]));
      }
    });
  }

  /**
   * Upload audio file and get word timings.
   */
  public async uploadAudioWithTimings(
    endpoint: string,
    audioFile: File | Blob,
    sentenceId: number
  ): Promise<AudioUploadResponse> {
    const formData = new FormData();
    formData.append('audio', audioFile);
    formData.append('sentence_id', String(sentenceId));

    const { data } = await this.api.post<AudioUploadResponse>(endpoint, formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    return data;
  }

  /**
   * Get waveform preview for a blob or file.
   */
  public async getWaveformPreview(audio: Blob | File): Promise<number[]> {
    const context = await this.initAudioContext();
    const arrayBuffer = await audio.arrayBuffer();
    const audioBuffer = await context.decodeAudioData(arrayBuffer);
    const channelData = audioBuffer.getChannelData(0);
    const samples = 100;
    const blockSize = Math.floor(channelData.length / samples);
    const points = [];

    for (let i = 0; i < samples; i++) {
      const start = blockSize * i;
      let sum = 0;
      for (let j = 0; j < blockSize; j++) {
        sum += Math.abs(channelData[start + j]);
      }
      points.push(sum / blockSize);
    }

    return points;
  }

  /**
   * Clean up resources.
   */
  public dispose(): void {
    if (this.audioContext) {
      this.audioContext.close();
      this.audioContext = null;
    }
    if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
      this.mediaRecorder.stream.getTracks().forEach(track => track.stop());
      this.mediaRecorder = null;
    }
    this.audioBuffer = null;
    this.recordedChunks = [];
  }
}

// Create a singleton instance with axios instance injected
export const audioService = new AudioService(AxiosInstance);