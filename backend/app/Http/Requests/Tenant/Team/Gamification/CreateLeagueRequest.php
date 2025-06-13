<?php

namespace App\Http\Requests\Tenant\Team\Gamification;

use Illuminate\Foundation\Http\FormRequest;

class CreateLeagueRequest extends FormRequest
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
            'leagues' => 'required|array|min:1',
            'leagues.*.name' => 'required|string|max:255|distinct',
            'leagues.*.tier' => [
                'required',
                'integer',
                'min:1',
                'distinct'
            ],
            'leagues.*.requirements' => 'required|array',
            'leagues.*.requirements.min_xp' => 'required|integer|min:0',
            'leagues.*.requirements.promotion_rank' => 'required|integer|min:1',
            'leagues.*.rewards' => 'required|array',
            'leagues.*.rewards.weekly_gems' => 'required|integer|min:0'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'leagues.*.name.distinct' => 'Each league must have a unique name',
            'leagues.*.tier.distinct' => 'Each league must have a unique tier level',
            'leagues.*.requirements.min_xp.min' => 'Minimum XP requirement cannot be negative',
            'leagues.*.rewards.weekly_gems.min' => 'Weekly gem rewards cannot be negative'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'leagues' => 'leagues configuration',
            'leagues.*.name' => 'league name',
            'leagues.*.tier' => 'league tier',
            'leagues.*.requirements' => 'league requirements',
            'leagues.*.rewards' => 'league rewards'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateTierProgression($validator);
            $this->validateRewardProgression($validator);
        });
    }

    /**
     * Validate that tiers form a proper progression.
     */
    protected function validateTierProgression($validator): void
    {
        $leagues = collect($this->leagues)->sortBy('tier');
        
        // Check if tiers are sequential
        $previousTier = 0;
        foreach ($leagues as $index => $league) {
            if ($league['tier'] !== $previousTier + 1) {
                $validator->errors()->add(
                    "leagues.{$index}.tier",
                    'League tiers must be sequential starting from 1'
                );
                return;
            }
            $previousTier = $league['tier'];
        }

        // Check XP requirements progression
        $previousXp = -1;
        foreach ($leagues as $index => $league) {
            if ($league['requirements']['min_xp'] <= $previousXp) {
                $validator->errors()->add(
                    "leagues.{$index}.requirements.min_xp",
                    'Each league must require more XP than the previous tier'
                );
                return;
            }
            $previousXp = $league['requirements']['min_xp'];
        }
    }

    /**
     * Validate that rewards follow a proper progression.
     */
    protected function validateRewardProgression($validator): void
    {
        $leagues = collect($this->leagues)->sortBy('tier');
        
        $previousGems = -1;
        foreach ($leagues as $index => $league) {
            if ($league['rewards']['weekly_gems'] <= $previousGems) {
                $validator->errors()->add(
                    "leagues.{$index}.rewards.weekly_gems",
                    'Each league must offer more weekly gems than the previous tier'
                );
                return;
            }
            $previousGems = $league['rewards']['weekly_gems'];
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('leagues')) {
            $leagues = collect($this->leagues)->map(function ($league) {
                return array_merge($league, [
                    'requirements' => array_merge($league['requirements'] ?? [], [
                        'min_xp' => (int) ($league['requirements']['min_xp'] ?? 0),
                        'promotion_rank' => (int) ($league['requirements']['promotion_rank'] ?? 1)
                    ]),
                    'rewards' => array_merge($league['rewards'] ?? [], [
                        'weekly_gems' => (int) ($league['rewards']['weekly_gems'] ?? 0)
                    ])
                ]);
            })->all();

            $this->merge(['leagues' => $leagues]);
        }
    }
}