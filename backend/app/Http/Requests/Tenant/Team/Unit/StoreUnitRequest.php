<?php

namespace App\Http\Requests\Tenant\Team\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
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
            'learning_path_id' => [
                'required',
                'integer',
                Rule::exists('learning_paths', 'id')
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'order' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('units')
                    ->where('learning_path_id', $this->learning_path_id)
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'learning_path_id.required' => 'The learning path is required.',
            'learning_path_id.exists' => 'The selected learning path does not exist.',
            'title.required' => 'A unit title is required.',
            'title.max' => 'The title cannot be longer than 255 characters.',
            'description.required' => 'A description of the unit is required.',
            'order.required' => 'The unit order is required.',
            'order.unique' => 'This order number is already taken in this learning path.',
            'order.min' => 'The order must be at least 1.',
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
     * Get the next available order number for the learning path
     */
    private function getNextOrder(): int
    {
        if (!$this->has('learning_path_id')) {
            return 1;
        }

        return \App\Models\Tenants\Unit::where('learning_path_id', $this->learning_path_id)
            ->max('order') + 1;
    }
}
