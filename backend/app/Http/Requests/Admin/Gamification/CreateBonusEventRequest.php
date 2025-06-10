<?php

namespace App\Http\Requests\Admin\Gamification;

use Illuminate\Foundation\Http\FormRequest;

class CreateBonusEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'start_date' => [
                'required',
                'date',
                'after_or_equal:today'
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date',
                'before:' . now()->addMonths(3)->format('Y-m-d')
            ],
            'bonuses' => 'required|array',
            'bonuses.xp_multiplier' => 'nullable|numeric|min:1|max:3',
            'bonuses.gems_multiplier' => 'nullable|numeric|min:1|max:3',
            'bonuses.streak_points_multiplier' => 'nullable|numeric|min:1|max:2',
            'conditions' => 'nullable|array',
            'conditions.min_level' => 'nullable|integer|min:1',
            'conditions.days_of_week' => 'nullable|array',
            'conditions.days_of_week.*' => 'integer|between:1,7',
            'conditions.time_range' => 'nullable|array',
            'conditions.time_range.start' => 'required_with:conditions.time_range|integer|between:0,23',
            'conditions.time_range.end' => 'required_with:conditions.time_range|integer|between:0,23|gt:conditions.time_range.start',
            'is_active' => 'boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'end_date.before' => 'Bonus events cannot be scheduled more than 3 months in advance',
            'bonuses.xp_multiplier.max' => 'XP multiplier cannot exceed 3x',
            'bonuses.gems_multiplier.max' => 'Gems multiplier cannot exceed 3x',
            'bonuses.streak_points_multiplier.max' => 'Streak points multiplier cannot exceed 2x',
            'conditions.days_of_week.*.between' => 'Days must be between 1 (Monday) and 7 (Sunday)',
            'conditions.time_range.start.between' => 'Time range must use 24-hour format (0-23)',
            'conditions.time_range.end.gt' => 'End time must be after start time'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateEventDuration($validator);
            $this->validateBonusBalance($validator);
            $this->validateOverlappingEvents($validator);
        });
    }

    /**
     * Validate event duration based on bonus values.
     */
    protected function validateEventDuration($validator): void
    {
        $startDate = new \DateTime($this->start_date);
        $endDate = new \DateTime($this->end_date);
        $duration = $startDate->diff($endDate);

        $maxMultiplier = max([
            $this->bonuses['xp_multiplier'] ?? 1,
            $this->bonuses['gems_multiplier'] ?? 1,
            $this->bonuses['streak_points_multiplier'] ?? 1
        ]);

        // Higher multipliers should have shorter durations
        $maxDuration = match (true) {
            $maxMultiplier > 2.5 => 3, // 3 days max for high multipliers
            $maxMultiplier > 2.0 => 5,
            $maxMultiplier > 1.5 => 7,
            default => 14 // Up to 2 weeks for small bonuses
        };

        if ($duration->days > $maxDuration) {
            $validator->errors()->add(
                'end_date',
                "Events with {$maxMultiplier}x multiplier cannot exceed {$maxDuration} days"
            );
        }
    }

    /**
     * Validate that bonuses are balanced.
     */
    protected function validateBonusBalance($validator): void
    {
        $multipliers = array_filter([
            $this->bonuses['xp_multiplier'] ?? null,
            $this->bonuses['gems_multiplier'] ?? null,
            $this->bonuses['streak_points_multiplier'] ?? null
        ]);

        if (empty($multipliers)) {
            $validator->errors()->add('bonuses', 'At least one bonus multiplier is required');
            return;
        }

        // Calculate total bonus value
        $totalBonus = array_sum(array_map(function ($multiplier) {
            return $multiplier - 1; // Convert multiplier to bonus percentage
        }, $multipliers));

        // Check if total bonus is reasonable
        if ($totalBonus > 4) { // More than 400% total bonus
            $validator->errors()->add(
                'bonuses',
                'Total combined bonus multipliers are too high'
            );
        }
    }

    /**
     * Validate that event doesn't overlap with similar events.
     */
    protected function validateOverlappingEvents($validator): void
    {
        $overlapping = \App\Models\Tenants\BonusEvent::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereBetween('start_date', [$this->start_date, $this->end_date])
                    ->orWhereBetween('end_date', [$this->start_date, $this->end_date])
                    ->orWhere(function ($q) {
                        $q->where('start_date', '<=', $this->start_date)
                            ->where('end_date', '>=', $this->end_date);
                    });
            })
            ->exists();

        if ($overlapping) {
            $validator->errors()->add(
                'start_date',
                'This event overlaps with another active bonus event'
            );
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('bonuses')) {
            $bonuses = array_map(function ($value) {
                return is_numeric($value) ? (float) $value : $value;
            }, $this->bonuses);

            $this->merge(['bonuses' => $bonuses]);
        }

        if ($this->has('conditions')) {
            $conditions = $this->conditions;
            if (isset($conditions['days_of_week'])) {
                $conditions['days_of_week'] = array_map('intval', $conditions['days_of_week']);
            }
            if (isset($conditions['time_range'])) {
                $conditions['time_range'] = array_map('intval', $conditions['time_range']);
            }

            $this->merge(['conditions' => $conditions]);
        }
    }
}