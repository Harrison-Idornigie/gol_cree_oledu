<?php

namespace App\Http\Requests\Admin\Exercise;

use Illuminate\Foundation\Http\FormRequest;

class StoreExerciseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'required|string',
            'type' => 'required|string|in:multiple_choice,fill_in_blank,matching,short_answer,long_answer',
            'difficulty' => 'required|string|in:beginner,intermediate,advanced',
            'status' => 'required|string|in:draft,published,archived',
            'section_id' => 'nullable|exists:sections,id',
            'order' => 'nullable|integer|min:1',
            'options' => 'nullable|array',
            'correct_answer' => 'nullable',
            'feedback' => 'nullable|string',
            'points' => 'nullable|integer|min:1',
            'metadata' => 'nullable|array'
        ];
    }
}
