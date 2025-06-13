<?php

namespace App\Http\Requests\Tenant\Team\Lesson;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonRequest extends FormRequest
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
                'sometimes',
                'integer',
                Rule::exists('units', 'id')
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'order' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('lessons')
                    ->where('unit_id', $this->unit_id ?? $this->lesson->unit_id)
                    ->ignore($this->lesson->id)
            ],
            'vocabulary_items' => ['sometimes', 'array'],
            'vocabulary_items.*.id' => ['sometimes', 'integer', 'exists:vocabulary_items,id'],
            'vocabulary_items.*.word' => ['required_with:vocabulary_items', 'string', 'max:255'],
            'vocabulary_items.*.translation' => ['required_with:vocabulary_items', 'string', 'max:255'],
            'vocabulary_items.*.example' => ['sometimes', 'string'],
            'vocabulary_items.*._remove' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'unit_id.exists' => 'The selected unit does not exist.',
            'title.required' => 'A lesson title is required.',
            'title.max' => 'The title cannot be longer than 255 characters.',
            'description.required' => 'A description of the lesson is required.',
            'order.unique' => 'This order number is already taken in this unit.',
            'order.min' => 'The order must be at least 1.',
            'vocabulary_items.*.word.required_with' => 'Each vocabulary item must have a word.',
            'vocabulary_items.*.translation.required_with' => 'Each vocabulary item must have a translation.',
            'vocabulary_items.*.id.exists' => 'One or more vocabulary items do not exist.',
        ];
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        if ($this->has('order') && $this->order !== $this->lesson->order) {
            $this->reorderLessons();
        }

        // Handle vocabulary items updates
        if ($this->has('vocabulary_items')) {
            $this->prepareVocabularyItems();
        }
    }

    /**
     * Reorder lessons when the order changes
     */
    private function reorderLessons(): void
    {
        $unitId = $this->unit_id ?? $this->lesson->unit_id;
        $currentOrder = $this->lesson->order;
        $newOrder = $this->order;

        if ($newOrder > $currentOrder) {
            // Moving down: decrement orders between current and new
            \App\Models\Tenants\Lesson::where('unit_id', $unitId)
                ->whereBetween('order', [$currentOrder + 1, $newOrder])
                ->decrement('order');
        } else {
            // Moving up: increment orders between new and current
            \App\Models\Tenants\Lesson::where('unit_id', $unitId)
                ->whereBetween('order', [$newOrder, $currentOrder - 1])
                ->increment('order');
        }
    }

    /**
     * Prepare vocabulary items for updating
     */
    private function prepareVocabularyItems(): void
    {
        $this->merge([
            'vocabulary_items' => collect($this->vocabulary_items)
                ->map(function ($item) {
                    // Mark items for removal if _remove is true
                    if (isset($item['_remove']) && $item['_remove']) {
                        return ['id' => $item['id'], '_remove' => true];
                    }
                    return $item;
                })
                ->toArray()
        ]);
    }
}