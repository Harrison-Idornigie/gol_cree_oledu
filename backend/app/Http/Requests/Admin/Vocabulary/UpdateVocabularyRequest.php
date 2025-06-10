<?php

namespace App\Http\Requests\Admin\Vocabulary;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVocabularyRequest extends FormRequest
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
            'word' => ['sometimes', 'string', 'max:255'],
            'definition' => ['sometimes', 'string'],
            'example' => ['nullable', 'string'],
            'level' => ['sometimes', 'string', 'in:beginner,intermediate,advanced'],
            'category' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:draft,published,archived'],
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
            'word.max' => 'The word cannot exceed 255 characters.',
            'level.in' => 'The level must be beginner, intermediate, or advanced.',
            'category.max' => 'The category cannot exceed 255 characters.',
            'status.in' => 'The status must be draft, published, or archived.',
            'lesson_ids.*.exists' => 'One or more selected lessons do not exist.'
        ];
    }
}
