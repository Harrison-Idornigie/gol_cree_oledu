<?php

namespace App\Http\Requests\Tenant\Team\Language;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWordRequest extends FormRequest
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
        $word = $this->route('word');
        
        return [
            'language_id' => 'sometimes|exists:languages,id',
            'text' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('words')->where(function ($query) {
                    return $query->where('language_id', $this->language_id ?? $this->route('word')->language_id)
                        ->where('part_of_speech', $this->part_of_speech ?? $this->route('word')->part_of_speech);
                })->ignore($word->id)
            ],
            'pronunciation_key' => 'nullable|string|max:255',
            'part_of_speech' => 'sometimes|string|max:50',
            'metadata' => 'nullable|array',
            'metadata.difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
            'metadata.tags' => 'nullable|array',
            'metadata.tags.*' => 'string|max:50',
            'metadata.notes' => 'nullable|string',
            
            'translations' => 'sometimes|array',
            'translations.*.id' => 'nullable|exists:word_translations,id',
            'translations.*.language_id' => [
                'required_with:translations.*',
                'exists:languages,id',
                Rule::notIn([$this->language_id ?? $word->language_id])
            ],
            'translations.*.text' => 'required_with:translations.*|string|max:255',
            'translations.*.pronunciation_key' => 'nullable|string|max:255',
            'translations.*.context_notes' => 'nullable|string',
            'translations.*.usage_examples' => 'nullable|array',
            'translations.*.usage_examples.*.example' => 'required|string|max:1000',
            'translations.*.usage_examples.*.translation' => 'required|string|max:1000',
            'translations.*.usage_examples.*.type' => 'required|string|in:common,formal,casual,idiom',
            'translations.*.translation_order' => 'nullable|integer|min:0',
            
            'pronunciation_audio' => 'nullable|file|mimes:mp3,wav|max:10240',
            'translations.*.pronunciation_audio' => 'nullable|file|mimes:mp3,wav|max:10240'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'metadata.difficulty' => 'difficulty level',
            'metadata.tags' => 'word tags',
            'metadata.notes' => 'additional notes',
            'translations.*.text' => 'translation text',
            'translations.*.pronunciation_key' => 'translation pronunciation',
            'translations.*.context_notes' => 'translation context',
            'translations.*.usage_examples' => 'usage examples',
            'pronunciation_audio' => 'pronunciation audio file',
            'translations.*.pronunciation_audio' => 'translation pronunciation audio'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'text.unique' => 'This word already exists in the selected language with the same part of speech.',
            'translations.*.language_id.not_in' => 'Translation language must be different from the word\'s language.'
        ];
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

        if ($this->has('translations')) {
            $translations = collect($this->translations)->map(function ($translation) {
                if (isset($translation['usage_examples']) && is_string($translation['usage_examples'])) {
                    $translation['usage_examples'] = json_decode($translation['usage_examples'], true);
                }
                return $translation;
            })->all();

            $this->merge(['translations' => $translations]);
        }
    }
}