<?php

namespace App\Http\Requests\Admin\Vocabulary;

use Illuminate\Foundation\Http\FormRequest;

class StoreVocabularyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled via middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'word' => ['required', 'string', 'max:255'],
            'definition' => ['required', 'string'],
            'example' => ['nullable', 'string'],
            'level' => ['required', 'string', 'in:beginner,intermediate,advanced'],
            'category' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'metadata' => ['nullable', 'array'],
            'lesson_ids' => ['nullable', 'array'],
            'lesson_ids.*' => ['exists:lessons,id']
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'word.required' => 'The vocabulary word is required.',
            'word.max' => 'The word cannot exceed 255 characters.',
            'definition.required' => 'The definition is required.',
            'level.required' => 'The difficulty level is required.',
            'level.in' => 'The level must be beginner, intermediate, or advanced.',
            'category.required' => 'The category is required.',
            'category.max' => 'The category cannot exceed 255 characters.',
            'status.required' => 'The status is required.',
            'status.in' => 'The status must be draft, published, or archived.',
            'lesson_ids.*.exists' => 'One or more selected lessons do not exist.'
        ];
    }
}
