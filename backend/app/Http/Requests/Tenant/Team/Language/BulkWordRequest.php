<?php

namespace App\Http\Requests\Tenant\Team\Language;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkWordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('team') || $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $operation = $this->input('operation', 'create');
        
        $baseRules = [
            'operation' => 'required|string|in:create,update,delete',
            'words' => 'required|array|min:1|max:100', // Limit bulk operations
        ];

        if ($operation === 'delete') {
            return array_merge($baseRules, [
                'words.*' => 'required|exists:words,id',
            ]);
        }

        if ($operation === 'update') {
            return array_merge($baseRules, [
                'words.*.id' => 'required|exists:words,id',
                'words.*.language_id' => 'sometimes|exists:languages,id',
                'words.*.text' => 'sometimes|string|max:255',
                'words.*.pronunciation_key' => 'nullable|string|max:255',
                'words.*.part_of_speech' => 'sometimes|string|max:50',
                'words.*.metadata' => 'nullable|array',
                'words.*.metadata.difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
                'words.*.metadata.tags' => 'nullable|array',
                'words.*.metadata.tags.*' => 'string|max:50',
                'words.*.metadata.notes' => 'nullable|string',
            ]);
        }

        // Create operation
        return array_merge($baseRules, [
            'words.*.language_id' => 'required|exists:languages,id',
            'words.*.text' => 'required|string|max:255',
            'words.*.pronunciation_key' => 'nullable|string|max:255',
            'words.*.part_of_speech' => 'nullable|string|max:50',
            'words.*.metadata' => 'nullable|array',
            'words.*.metadata.difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
            'words.*.metadata.tags' => 'nullable|array',
            'words.*.metadata.tags.*' => 'string|max:50',
            'words.*.metadata.notes' => 'nullable|string',
            
            'words.*.translations' => 'nullable|array',
            'words.*.translations.*.language_id' => [
                'required_with:words.*.translations.*',
                'exists:languages,id'
            ],
            'words.*.translations.*.text' => 'required_with:words.*.translations.*|string|max:255',
            'words.*.translations.*.pronunciation_key' => 'nullable|string|max:255',
            'words.*.translations.*.context_notes' => 'nullable|string',
            'words.*.translations.*.usage_examples' => 'nullable|array',
            'words.*.translations.*.usage_examples.*.example' => 'required|string|max:1000',
            'words.*.translations.*.usage_examples.*.translation' => 'required|string|max:1000',
            'words.*.translations.*.usage_examples.*.type' => 'required|string|in:common,formal,casual,idiom',
            'words.*.translations.*.translation_order' => 'nullable|integer|min:0',
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'words.*.text' => 'word text',
            'words.*.language_id' => 'word language',
            'words.*.pronunciation_key' => 'pronunciation key',
            'words.*.part_of_speech' => 'part of speech',
            'words.*.metadata.difficulty' => 'difficulty level',
            'words.*.metadata.tags' => 'word tags',
            'words.*.metadata.notes' => 'additional notes',
            'words.*.translations.*.text' => 'translation text',
            'words.*.translations.*.language_id' => 'translation language',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'words.min' => 'At least one word is required for bulk operations.',
            'words.max' => 'Maximum 100 words allowed per bulk operation.',
            'operation.in' => 'Operation must be one of: create, update, delete.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('words')) {
            $words = collect($this->words)->map(function ($word) {
                // Handle metadata JSON string
                if (isset($word['metadata']) && is_string($word['metadata'])) {
                    $word['metadata'] = json_decode($word['metadata'], true);
                }

                // Handle translations
                if (isset($word['translations'])) {
                    $word['translations'] = collect($word['translations'])->map(function ($translation) {
                        if (isset($translation['usage_examples']) && is_string($translation['usage_examples'])) {
                            $translation['usage_examples'] = json_decode($translation['usage_examples'], true);
                        }
                        return $translation;
                    })->all();
                }

                return $word;
            })->all();

            $this->merge(['words' => $words]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $operation = $this->input('operation');
            $words = $this->input('words', []);

            if ($operation === 'create') {
                $this->validateUniqueWordsInBatch($validator, $words);
            }
        });
    }

    /**
     * Validate that words in the batch are unique.
     */
    private function validateUniqueWordsInBatch($validator, array $words): void
    {
        $seen = [];
        
        foreach ($words as $index => $word) {
            $key = ($word['language_id'] ?? '') . '|' . ($word['text'] ?? '') . '|' . ($word['part_of_speech'] ?? '');
            
            if (in_array($key, $seen)) {
                $validator->errors()->add(
                    "words.{$index}.text",
                    'Duplicate word found in batch: ' . ($word['text'] ?? 'unknown')
                );
            }
            
            $seen[] = $key;
        }
    }
}