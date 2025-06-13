<?php

namespace App\Http\Requests\Tenant\Team\LearningPath;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLearningPathRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user has admin membership and the learning path exists
        return $this->user()->can('manage-learning-paths');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'target_level' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['beginner', 'intermediate', 'advanced', 'native']),
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['draft', 'published', 'archived']),
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A learning path title is required.',
            'title.max' => 'The title cannot be longer than 255 characters.',
            'description.required' => 'A description of the learning path is required.',
            'target_level.required' => 'Please specify the target proficiency level.',
            'target_level.in' => 'The target level must be beginner, intermediate, advanced, or native.',
            'status.required' => 'Please specify the status of the learning path.',
            'status.in' => 'The status must be draft, published, or archived.',
        ];
    }

    /**
     * Get data to be validated from the request.
     */
    public function validationData(): array
    {
        $data = parent::validationData();
        
        // Only include fields that were actually submitted
        return array_filter($data, function ($value) {
            return $value !== null;
        });
    }
}
