<?php

namespace App\Services\Tenants\Media;

use App\Models\Tenants\{Sentence, Word, SentenceWord};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * Handles audio file processing and word timing management.
 *
 * Requirements:
 * - FFmpeg/FFprobe must be installed on the server
 *
 * Usage:
 * ```php
 * $service = app(AudioProcessingService::class);
 * $result = $service->processSentenceAudio($sentence, $request->file('audio'));
 * ```
 */
class AudioProcessingService
{
    public function __construct()
    {
        // No dependencies needed - using FFmpeg directly
    }

    /**
     * Process a sentence audio file and save word timings.
     */
    public function processSentenceAudio(
        Sentence $sentence,
        UploadedFile $audioFile,
        bool $isSlowVersion = false
    ): array {
        // Get audio duration using getID3
        $duration = $this->getAudioDuration($audioFile);

        // Add to media collection
        $collectionName = $isSlowVersion ? 'slow_pronunciation' : 'pronunciation';
        $fileName = "{$sentence->id}_" . ($isSlowVersion ? 'slow' : 'normal') . ".mp3";

        $mediaFile = $sentence->addMedia($audioFile)
            ->usingFileName($fileName)
            ->withCustomProperties([
                'duration' => $duration,
                'is_slow' => $isSlowVersion,
                'word_count' => $sentence->words()->count()
            ])
            ->toMediaCollection($collectionName);

        return [
            'audio_url' => $mediaFile->getUrl(),
            'duration' => $duration,
            'collection' => $collectionName,
            'media_id' => $mediaFile->id
        ];
    }

    /**
     * Process word pronunciation audio.
     */
    public function processWordAudio(
        Word $word,
        UploadedFile $audioFile,
        ?string $languageCode = null
    ): array {
        $duration = $this->getAudioDuration($audioFile);

        $collection = $languageCode ? "pronunciation_{$languageCode}" : 'pronunciation';
        $fileName = "{$word->id}" . ($languageCode ? "_{$languageCode}" : '') . ".mp3";

        $mediaFile = $word->addMedia($audioFile)
            ->usingFileName($fileName)
            ->withCustomProperties([
                'duration' => $duration,
                'language' => $languageCode,
                'word_text' => $word->text
            ])
            ->toMediaCollection($collection);

        return [
            'audio_url' => $mediaFile->getUrl(),
            'duration' => $duration,
            'collection' => $collection,
            'media_id' => $mediaFile->id
        ];
    }

    /**
     * Process word translation pronunciation audio.
     */
    public function processTranslationAudio(
        $translation,
        UploadedFile $audioFile,
        ?string $languageCode = null
    ): array {
        $duration = $this->getAudioDuration($audioFile);

        $collection = $languageCode ? "pronunciation_{$languageCode}" : 'pronunciation';
        $fileName = "{$translation->id}" . ($languageCode ? "_{$languageCode}" : '') . ".mp3";

        $mediaFile = $translation->addMedia($audioFile)
            ->usingFileName($fileName)
            ->withCustomProperties([
                'duration' => $duration,
                'language' => $languageCode,
                'translation_text' => $translation->text
            ])
            ->toMediaCollection($collection);

        return [
            'audio_url' => $mediaFile->getUrl(),
            'duration' => $duration,
            'collection' => $collection,
            'media_id' => $mediaFile->id
        ];
    }

    /**
     * Update word timings in a sentence.
     */
    public function updateWordTimings(
        Sentence $sentence,
        array $timings,
        float $audioDuration
    ): array {
        $stats = [
            'total_words' => count($timings),
            'total_duration' => $audioDuration,
            'timing_gaps' => [],
            'emphasis_points' => []
        ];

        // Sort timings by start time
        $sortedTimings = collect($timings)->sortBy('start_time');
        $previousTiming = null;

        foreach ($sortedTimings as $timing) {
            // Update the sentence_words pivot with timing info
            $sentence->words()->updateExistingPivot($timing['word_id'], [
                'start_time' => $timing['start_time'],
                'end_time' => $timing['end_time'],
                'metadata' => $timing['metadata'] ?? null
            ]);

            // Calculate gaps between words
            if ($previousTiming) {
                $gap = $timing['start_time'] - $previousTiming['end_time'];
                if ($gap > 0.1) { // Only record significant gaps
                    $stats['timing_gaps'][] = [
                        'between_words' => [
                            'first' => $previousTiming['word_id'],
                            'second' => $timing['word_id']
                        ],
                        'duration' => $gap
                    ];
                }
            }

            // Record emphasis points
            if (($timing['metadata']['emphasis'] ?? false) === true) {
                $stats['emphasis_points'][] = [
                    'word_id' => $timing['word_id'],
                    'time' => $timing['start_time']
                ];
            }

            $previousTiming = $timing;
        }

        // Calculate average word duration
        $stats['average_word_duration'] = collect($timings)->avg(function ($timing) {
            return $timing['end_time'] - $timing['start_time'];
        });

        return $stats;
    }

    /**
     * Get audio file duration using FFprobe.
     */
    protected function getAudioDuration(UploadedFile $file): float
    {
        try {
            $filePath = escapeshellarg($file->getPathname());
            $command = "ffprobe -v quiet -show_entries format=duration -of csv=p=0 {$filePath}";

            $output = shell_exec($command);

            if ($output === null || trim($output) === '') {
                throw new Exception('Could not determine audio file duration');
            }

            $duration = (float) trim($output);

            if ($duration <= 0) {
                throw new Exception('Invalid audio file duration');
            }

            return $duration;
        } catch (Exception $e) {
            throw new Exception('Failed to analyze audio file: ' . $e->getMessage());
        }
    }

    /**
     * Validate word timings against audio duration.
     */
    public function validateTimings(array $timings, float $audioDuration): bool
    {
        // Check if any timing exceeds audio duration
        $lastTiming = collect($timings)->sortByDesc('end_time')->first();
        if ($lastTiming['end_time'] > $audioDuration) {
            return false;
        }

        // Check for overlaps
        $sortedTimings = collect($timings)->sortBy('start_time');
        $previousEnd = 0;

        foreach ($sortedTimings as $timing) {
            if ($timing['start_time'] < $previousEnd) {
                return false;
            }
            $previousEnd = $timing['end_time'];
        }

        return true;
    }

    /**
     * Generate waveform data for visualization.
     */
    public function generateWaveformData(UploadedFile $file, int $samples = 100): array
    {
        try {
            // Validate audio file first using FFprobe
            $this->validateAudioFile($file);

            $tempPath = escapeshellarg($file->getPathname());
            $waveform = [];

            // Use FFmpeg to convert audio to raw samples
            $command = sprintf(
                'ffmpeg -i %s -f s16le -acodec pcm_s16le -ac 1 -ar 8000 - 2>/dev/null',
                $tempPath
            );

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];

            $process = proc_open($command, $descriptors, $pipes);

            if (!is_resource($process)) {
                throw new Exception('Failed to start FFmpeg process');
            }

            // Read raw audio data
            $rawAudio = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $status = proc_close($process);

            if ($status !== 0 || !$rawAudio) {
                throw new Exception('Failed to process audio file for waveform');
            }

            // Convert raw audio data to waveform points
            $data = unpack("s*", $rawAudio);
            $chunks = array_chunk($data, ceil(count($data) / $samples));

            foreach ($chunks as $chunk) {
                $value = array_sum(array_map('abs', $chunk)) / count($chunk);
                $waveform[] = $value / 32768; // Normalize to 0-1 range
            }

            return $waveform;
        } catch (Exception $e) {
            throw new Exception('Failed to generate waveform: ' . $e->getMessage());
        }
    }

    /**
     * Extract audio metadata using FFprobe.
     */
    public function extractMetadata(UploadedFile $file): array
    {
        try {
            $filePath = escapeshellarg($file->getPathname());
            $command = "ffprobe -v quiet -print_format json -show_format -show_streams {$filePath}";

            $output = shell_exec($command);

            if ($output === null) {
                throw new Exception('Failed to execute FFprobe command');
            }

            $data = json_decode($output, true);

            if (!$data || !isset($data['format'])) {
                throw new Exception('Invalid audio file or unsupported format');
            }

            $format = $data['format'];
            $audioStream = null;

            // Find the first audio stream
            if (isset($data['streams'])) {
                foreach ($data['streams'] as $stream) {
                    if ($stream['codec_type'] === 'audio') {
                        $audioStream = $stream;
                        break;
                    }
                }
            }

            return [
                'duration' => isset($format['duration']) ? (float) $format['duration'] : null,
                'format' => $format['format_name'] ?? null,
                'sample_rate' => $audioStream['sample_rate'] ?? null,
                'channels' => $audioStream['channels'] ?? null,
                'bitrate' => isset($format['bit_rate']) ? (int) $format['bit_rate'] : null,
                'encoder' => $audioStream['codec_name'] ?? null,
                'lossless' => $this->isLosslessFormat($audioStream['codec_name'] ?? '')
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to extract audio metadata: ' . $e->getMessage());
        }
    }

    /**
     * Check if audio format is lossless.
     */
    protected function isLosslessFormat(string $codec): bool
    {
        $losslessCodecs = ['flac', 'alac', 'ape', 'wav', 'aiff'];
        return in_array(strtolower($codec), $losslessCodecs);
    }

    /**
     * Validate that the uploaded file is a valid audio file.
     */
    protected function validateAudioFile(UploadedFile $file): void
    {
        $filePath = escapeshellarg($file->getPathname());
        $command = "ffprobe -v quiet -show_entries stream=codec_type -of csv=p=0 {$filePath}";

        $output = shell_exec($command);

        if ($output === null || strpos($output, 'audio') === false) {
            throw new Exception('Invalid audio file format');
        }
    }
}
