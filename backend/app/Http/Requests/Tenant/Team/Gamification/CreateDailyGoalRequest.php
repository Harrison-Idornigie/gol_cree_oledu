<?php

namespace App\Http\Requests\Tenant\Team\Gamification;

use Illuminate\Foundation\Http\FormRequest;

class CreateDailyGoalRequest extends FormRequest
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
            'options' => 'required|array|min:1|max:5',
            'options.*.name' => 'required|string|max:255|distinct',
            'options.*.xp_target' => [
                'required',
                'integer',
                'min:10',
                'max:1000',
                'distinct'
            ],
            'options.*.rewards' => 'required|array',
            'options.*.rewards.streak_points' => 'required|integer|min:1|max:10',
            'options.*.rewards.gems' => 'nullable|integer|min:0|max:20',
            'options.*.rewards.xp_bonus' => 'nullable|integer|min:0|max:50',
            'replace_existing' => 'boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'options.*.name.distinct' => 'Each daily goal must have a unique name',
            'options.*.xp_target.distinct' => 'Each daily goal must have a unique XP target',
            'options.*.xp_target.min' => 'Daily goal XP target must be at least 10',
            'options.*.xp_target.max' => 'Daily goal XP target cannot exceed 1000',
            'options.max' => 'Cannot create more than 5 daily goal options'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateProgression($validator);
            $this->validateRewardsBalance($validator);
        });
    }

    /**
     * Validate that goals form a proper progression.
     */
    protected function validateProgression($validator): void
    {
        $options = collect($this->options)->sortBy('xp_target');

        // Check XP target progression
        $previousXp = 0;
        $previousStreak = 0;
        foreach ($options as $index => $option) {
            // Each tier should require at least 50% more XP than the previous
            $minimumXp = $previousXp * 1.5;
            if ($previousXp > 0 && $option['xp_target'] < $minimumXp) {
                $validator->errors()->add(
                    "options.{$index}.xp_target",
                    "This goal's XP target should be at least {$minimumXp}"
                );
                return;
            }

            // Check streak points progression
            if ($option['rewards']['streak_points'] <= $previousStreak) {
                $validator->errors()->add(
                    "options.{$index}.rewards.streak_points",
                    'Higher goals must award more streak points'
                );
                return;
            }

            $previousXp = $option['xp_target'];
            $previousStreak = $option['rewards']['streak_points'];
        }
    }

    /**
     * Validate that rewards are balanced.
     */
    protected function validateRewardsBalance($validator): void
    {
        foreach ($this->options as $index => $option) {
            $rewards = $option['rewards'];
            $xpTarget = $option['xp_target'];

            // Calculate total value of rewards
            $totalValue = 
                ($rewards['streak_points'] * 10) + // Base value for streak points
                ($rewards['gems'] ?? 0) * 5 + // Gem value
                ($rewards['xp_bonus'] ?? 0); // Direct XP bonus

            // Rewards should not exceed 20% of XP target in total value
            $maxValue = $xpTarget * 0.2;
            if ($totalValue > $maxValue) {
                $validator->errors()->add(
                    "options.{$index}.rewards",
                    'Total rewards value is too high for this XP target'
                );
            }

            // Individual reward limits based on XP target
            $maxGems = floor($xpTarget / 50); // 1 gem per 50 XP
            if (isset($rewards['gems']) && $rewards['gems'] > $maxGems) {
                $validator->errors()->add(
                    "options.{$index}.rewards.gems",
                    "Gem reward cannot exceed {$maxGems} for this XP target"
                );
            }

            $maxXpBonus = floor($xpTarget * 0.1); // 10% of XP target
            if (isset($rewards['xp_bonus']) && $rewards['xp_bonus'] > $maxXpBonus) {
                $validator->errors()->add(
                    "options.{$index}.rewards.xp_bonus",
                    "XP bonus cannot exceed {$maxXpBonus} for this XP target"
                );
            }
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('options')) {
            $options = collect($this->options)->map(function ($option) {
                return [
                    'name' => $option['name'],
                    'xp_target' => (int) $option['xp_target'],
                    'rewards' => [
                        'streak_points' => (int) $option['rewards']['streak_points'],
                        'gems' => isset($option['rewards']['gems']) 
                            ? (int) $option['rewards']['gems'] 
                            : null,
                        'xp_bonus' => isset($option['rewards']['xp_bonus'])
                            ? (int) $option['rewards']['xp_bonus']
                            : null
                    ]
                ];
            })->all();

            $this->merge(['options' => $options]);
        }
    }
}