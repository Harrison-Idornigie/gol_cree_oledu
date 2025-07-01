<?php

namespace App\Http\Requests\Tenant\Team\Lesson;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage-learning-paths');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'order' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('lessons')
                    ->where('unit_id', $this->unit_id)
            ],
            'guidebook_items' => ['sometimes', 'array'],
            'guidebook_items.*.word' => ['required_with:guidebook_items', 'string', 'max:255'],
            'guidebook_items.*.translation' => ['required_with:guidebook_items', 'string', 'max:255'],
            'guidebook_items.*.example' => ['sometimes', 'string'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'unit_id.required' => 'The unit is required.',
            'unit_id.exists' => 'The selected unit does not exist.',
            'title.required' => 'A lesson title is required.',
            'title.max' => 'The title cannot be longer than 255 characters.',
            'description.required' => 'A description of the lesson is required.',
            'order.required' => 'The lesson order is required.',
            'order.unique' => 'This order number is already taken in this unit.',
            'order.min' => 'The order must be at least 1.',
            'guidebook_items.*.word.required_with' => 'Each guidebook item must have a word.',
            'guidebook_items.*.translation.required_with' => 'Each guidebook item must have a translation.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If order is not provided, set it to the next available order number
        if (!$this->has('order')) {
            $this->merge([
                'order' => $this->getNextOrder()
            ]);
        }
    }

    /**
     * Get the next available order number for the unit
     */
    private function getNextOrder(): int
    {
        if (!$this->has('unit_id')) {
            return 1;
        }

        return \App\Models\Tenants\Lesson::where('unit_id', $this->unit_id)
            ->max('order') + 1;
    }
}
