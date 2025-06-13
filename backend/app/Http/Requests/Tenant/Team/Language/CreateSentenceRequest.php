<?php

namespace App\Http\Requests\Tenant\Team\Language;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Tenants\Word;

class CreateSentenceRequest extends FormRequest
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
            'language_id' => 'required|exists:languages,id',
            'text' => 'required|string|max:1000',
            'pronunciation_key' => 'nullable|string',
            
            'metadata' => 'nullable|array',
            'metadata.difficulty' => 'required|string|in:beginner,intermediate,advanced',
            'metadata.tags' => 'nullable|array',
            'metadata.tags.*' => 'string|max:50',
            'metadata.notes' => 'nullable|string',
            'metadata.grammar_points' => 'nullable|array',
            'metadata.grammar_points.*' => 'string|max:100',
            
            'words' => 'required|array|min:1',
            'words.*.word_id' => [
                'required',
                'exists:words,id',
                function ($attribute, $value, $fail) {
                    $word = Word::find($value);
                    if ($word && $word->language_id !== $this->input('language_id')) {
                        $fail('The word must belong to the same language as the sentence.');
                    }
                }
            ],
            'words.*.position' => 'required|integer|min:0|distinct',
            'words.*.start_time' => 'nullable|numeric|min:0',
            'words.*.end_time' => 'nullable|numeric|gt:words.*.start_time',
            'words.*.metadata' => 'nullable|array',
            
            'translations' => 'array',
            'translations.*.language_id' => [
                'required',
                'exists:languages,id',
                Rule::notIn([$this->language_id])
            ],
            'translations.*.text' => 'required|string|max:1000',
            'translations.*.pronunciation_key' => 'nullable|string',
            'translations.*.context_notes' => 'nullable|string',
            
            'audio' => [
                'nullable',
                'file',
                'mimes:mp3,wav',
                'max:10240',
                Rule::requiredIf(function () {
                    return in_array($this->input('metadata.difficulty'), ['beginner', 'intermediate']);
                })
            ],
            
            'audio_slow' => [
                'nullable',
                'file',
                'mimes:mp3,wav',
                'max:10240',
                Rule::requiredIf(function () {
                    return $this->input('metadata.difficulty') === 'beginner';
                })
            ],
            
            'translations.*.audio' => 'nullable|file|mimes:mp3,wav|max:10240'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'metadata.difficulty' => 'difficulty level',
            'metadata.tags' => 'sentence tags',
            'metadata.grammar_points' => 'grammar points',
            'words.*.word_id' => 'word',
            'words.*.position' => 'word position',
            'words.*.start_time' => 'word start time',
            'words.*.end_time' => 'word end time',
            'translations.*.text' => 'translation text',
            'translations.*.context_notes' => 'translation context',
            'audio' => 'pronunciation audio file',
            'audio_slow' => 'slow pronunciation audio file',
            'translations.*.audio' => 'translation pronunciation audio'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'words.required' => 'A sentence must contain at least one word.',
            'words.*.word_id.exists' => 'One or more words do not exist in the database.',
            'words.*.position.distinct' => 'Each word must have a unique position in the sentence.',
            'translations.*.language_id.not_in' => 'Translation language must be different from the sentence\'s language.',
            'audio.required_if' => 'Audio pronunciation is required for beginner and intermediate level sentences.',
            'audio_slow.required_if' => 'Slow audio pronunciation is required for beginner level sentences.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateWordOrder($validator);
            $this->validateTimingConsistency($validator);
        });
    }

    /**
     * Validate that word order matches the sentence text.
     */
    protected function validateWordOrder($validator): void
    {
        if ($validator->errors()->hasAny(['text', 'words'])) {
            return;
        }

        $words = collect($this->words)->sortBy('position')->pluck('word_id');
        $wordTexts = Word::findMany($words)->pluck('text');
        
        // Simple space-separated comparison (can be made more sophisticated for different languages)
        $sentenceWords = collect(preg_split('/\s+/', trim($this->text)));
        
        if ($sentenceWords->count() !== $wordTexts->count()) {
            $validator->errors()->add(
                'words',
                'The number of words does not match the sentence text.'
            );
        }
    }

    /**
     * Validate timing consistency when provided.
     */
    protected function validateTimingConsistency($validator): void
    {
        if (!$this->hasTimingInfo()) {
            return;
        }

        $words = collect($this->words)->sortBy('position');
        $previousEnd = 0;

        foreach ($words as $index => $word) {
            if (isset($word['start_time'])) {
                if ($word['start_time'] < $previousEnd) {
                    $validator->errors()->add(
                        "words.{$index}.start_time",
                        'Word timing overlaps with the previous word.'
                    );
                }
                $previousEnd = $word['end_time'];
            }
        }
    }

    /**
     * Check if timing information is provided.
     */
    protected function hasTimingInfo(): bool
    {
        return collect($this->words)->contains(function ($word) {
            return isset($word['start_time']) || isset($word['end_time']);
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true)
            ]);
        }

        if ($this->has('words')) {
            $words = collect($this->words)->map(function ($word) {
                if (isset($word['metadata']) && is_string($word['metadata'])) {
                    $word['metadata'] = json_decode($word['metadata'], true);
                }
                return $word;
            })->all();

            $this->merge(['words' => $words]);
        }
    }
}