<?php

namespace App\Http\Requests\Tenant\Team\Gamification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStreakRulesRequest extends FormRequest
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
            'freeze_cost' => 'required|integer|min:0',
            'repair_window_hours' => 'required|integer|min:1|max:72',
            'bonus_schedule' => 'required|array|min:1',
            'bonus_schedule.*.days' => [
                'required',
                'integer',
                'min:1',
                'distinct',
                'lt:1000'
            ],
            'bonus_schedule.*.gems' => 'required|integer|min:0',
            'xp_multipliers' => 'required|array|min:1',
            'xp_multipliers.*.days' => [
                'required',
                'integer',
                'min:1',
                'distinct',
                'lt:1000'
            ],
            'xp_multipliers.*.multiplier' => [
                'required',
                'numeric',
                'min:1',
                'max:5'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'bonus_schedule.*.days.distinct' => 'Each milestone in the bonus schedule must be unique',
            'xp_multipliers.*.days.distinct' => 'Each milestone in the XP multipliers must be unique',
            'xp_multipliers.*.multiplier.max' => 'XP multipliers cannot exceed 5x',
            'repair_window_hours.max' => 'Repair window cannot exceed 72 hours'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateProgressions($validator);
        });
    }

    /**
     * Validate that rewards and multipliers follow proper progressions.
     */
    protected function validateProgressions($validator): void
    {
        // Sort schedules by days
        $bonusSchedule = collect($this->bonus_schedule)->sortBy('days');
        $xpMultipliers = collect($this->xp_multipliers)->sortBy('days');

        // Validate bonus gems progression
        $previousGems = -1;
        foreach ($bonusSchedule as $index => $milestone) {
            if ($milestone['gems'] < $previousGems) {
                $validator->errors()->add(
                    "bonus_schedule.{$index}.gems",
                    'Later milestones must offer equal or more gems than earlier ones'
                );
                return;
            }
            $previousGems = $milestone['gems'];
        }

        // Validate XP multiplier progression
        $previousMultiplier = 0;
        foreach ($xpMultipliers as $index => $milestone) {
            if ($milestone['multiplier'] <= $previousMultiplier) {
                $validator->errors()->add(
                    "xp_multipliers.{$index}.multiplier",
                    'Later milestones must offer higher multipliers than earlier ones'
                );
                return;
            }
            $previousMultiplier = $milestone['multiplier'];
        }

        // Validate milestone spacing
        $this->validateMilestoneSpacing($validator, $bonusSchedule, 'bonus_schedule');
        $this->validateMilestoneSpacing($validator, $xpMultipliers, 'xp_multipliers');
    }

    /**
     * Validate that milestones are properly spaced.
     */
    protected function validateMilestoneSpacing($validator, $milestones, $field): void
    {
        $previousDays = 0;
        foreach ($milestones as $index => $milestone) {
            // Early milestones should be closer together
            $minimumGap = min(
                5, // Minimum 5 days gap
                max(1, floor($previousDays * 0.2)) // Or 20% of previous milestone
            );

            if ($milestone['days'] - $previousDays < $minimumGap) {
                $validator->errors()->add(
                    "{$field}.{$index}.days",
                    "Milestones must be at least {$minimumGap} days apart at this point"
                );
                return;
            }
            $previousDays = $milestone['days'];
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure numeric values are properly typed
        if ($this->has('bonus_schedule')) {
            $bonusSchedule = collect($this->bonus_schedule)->map(function ($milestone) {
                return [
                    'days' => (int) $milestone['days'],
                    'gems' => (int) $milestone['gems']
                ];
            })->all();

            $this->merge(['bonus_schedule' => $bonusSchedule]);
        }

        if ($this->has('xp_multipliers')) {
            $xpMultipliers = collect($this->xp_multipliers)->map(function ($milestone) {
                return [
                    'days' => (int) $milestone['days'],
                    'multiplier' => (float) $milestone['multiplier']
                ];
            })->all();

            $this->merge(['xp_multipliers' => $xpMultipliers]);
        }
    }
}