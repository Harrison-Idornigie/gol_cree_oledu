<?php

namespace App\Http\Requests\Tenant\Team\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
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
                'sometimes',
                'integer',
                Rule::exists('learning_paths', 'id')
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'order' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('units')
                    ->where('learning_path_id', $this->learning_path_id ?? $this->unit->learning_path_id)
                    ->ignore($this->unit->id)
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
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
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        if ($this->has('order') && $this->order !== $this->unit->order) {
            $this->reorderUnits();
        }
    }

    /**
     * Reorder units when the order changes
     */
    private function reorderUnits(): void
    {
        $learningPathId = $this->learning_path_id ?? $this->unit->learning_path_id;
        $currentOrder = $this->unit->order;
        $newOrder = $this->order;

        if ($newOrder > $currentOrder) {
            // Moving down: decrement orders between current and new
            \App\Models\Tenants\Unit::where('learning_path_id', $learningPathId)
                ->whereBetween('order', [$currentOrder + 1, $newOrder])
                ->decrement('order');
        } else {
            // Moving up: increment orders between new and current
            \App\Models\Tenants\Unit::where('learning_path_id', $learningPathId)
                ->whereBetween('order', [$newOrder, $currentOrder - 1])
                ->increment('order');
        }
    }
}
