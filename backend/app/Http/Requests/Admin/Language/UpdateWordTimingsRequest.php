<?php

namespace App\Http\Requests\Admin\Language;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Tenants\Sentence;

class UpdateWordTimingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'timings' => 'required|array|min:1',
            'timings.*.word_id' => [
                'required',
                'integer',
                Rule::exists('sentence_words', 'word_id')->where(function ($query) {
                    $query->where('sentence_id', $this->route('sentence')->id);
                })
            ],
            'timings.*.start_time' => 'required|numeric|min:0',
            'timings.*.end_time' => 'required|numeric|gt:timings.*.start_time',
            'timings.*.metadata' => 'nullable|array',
            'timings.*.metadata.emphasis' => 'nullable|boolean',
            'timings.*.metadata.pause_after' => 'nullable|numeric|min:0',
            'timings.*.metadata.pronunciation_notes' => 'nullable|string',
            'audio_duration' => 'required|numeric|min:0'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'timings.*.word_id.exists' => 'One or more words do not belong to this sentence.',
            'timings.*.start_time.min' => 'Start time cannot be negative.',
            'timings.*.end_time.gt' => 'End time must be greater than start time.',
            'timings.*.metadata.pause_after.min' => 'Pause duration cannot be negative.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateTimeRanges($validator);
            $this->validateCompleteness($validator);
            $this->validateAudioDuration($validator);
        });
    }

    /**
     * Validate that time ranges don't overlap.
     */
    protected function validateTimeRanges($validator): void
    {
        $timings = collect($this->timings)->sortBy('start_time');
        $previousTiming = null;

        foreach ($timings as $index => $timing) {
            if ($previousTiming) {
                if ($timing['start_time'] < $previousTiming['end_time']) {
                    $validator->errors()->add(
                        "timings.{$index}.start_time",
                        'Word timing overlaps with previous word.'
                    );
                }

                // Check for unreasonable gaps (more than 2 seconds)
                $gap = $timing['start_time'] - $previousTiming['end_time'];
                if ($gap > 2 && !($previousTiming['metadata']['pause_after'] ?? false)) {
                    $validator->errors()->add(
                        "timings.{$index}.start_time",
                        'Unreasonable gap detected between words.'
                    );
                }
            }

            $previousTiming = $timing;
        }
    }

    /**
     * Validate that all words in the sentence have timings.
     */
    protected function validateCompleteness($validator): void
    {
        /** @var Sentence */
        $sentence = $this->route('sentence');
        $wordCount = $sentence->words()->count();
        $timingCount = count($this->timings);

        if ($wordCount !== $timingCount) {
            $validator->errors()->add(
                'timings',
                "Timing information must be provided for all {$wordCount} words in the sentence."
            );
        }
    }

    /**
     * Validate that timings don't exceed audio duration.
     */
    protected function validateAudioDuration($validator): void
    {
        if ($validator->errors()->hasAny(['timings', 'audio_duration'])) {
            return;
        }

        $lastTiming = collect($this->timings)->sortByDesc('end_time')->first();
        if ($lastTiming['end_time'] > $this->audio_duration) {
            $validator->errors()->add(
                'timings',
                'Word timings cannot exceed the audio duration.'
            );
        }
    }

    /**
     * Get timing statistics.
     */
    public function getTimingStats(): array
    {
        $timings = collect($this->timings);

        return [
            'total_duration' => $this->audio_duration,
            'word_count' => $timings->count(),
            'average_word_duration' => $timings->avg(function ($timing) {
                return $timing['end_time'] - $timing['start_time'];
            }),
            'total_pause_time' => $this->calculateTotalPauseTime($timings),
            'words_with_emphasis' => $timings->filter(function ($timing) {
                return $timing['metadata']['emphasis'] ?? false;
            })->count()
        ];
    }

    /**
     * Calculate total pause time between words.
     */
    protected function calculateTotalPauseTime($timings): float
    {
        $pauseTime = 0;
        $timings = $timings->sortBy('start_time');
        $previousTiming = null;

        foreach ($timings as $timing) {
            if ($previousTiming) {
                $pauseTime += $timing['start_time'] - $previousTiming['end_time'];
            }
            $previousTiming = $timing;
        }

        return $pauseTime;
    }
}