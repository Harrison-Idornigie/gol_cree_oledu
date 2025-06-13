<?php

namespace App\Http\Requests\Tenant\Team\Gamification;

use App\Models\Tenants\Achievement;
use Illuminate\Foundation\Http\FormRequest;

class CreateAchievementRequest extends FormRequest
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
            'name' => 'required|string|max:255|unique:achievements',
            'description' => 'required|string|max:1000',
            'requirements' => ['required', 'array', 
                function ($attribute, $value, $fail) {
                    $rules = Achievement::getRequirementRules();
                    $validator = validator($value, $rules);
                    if ($validator->fails()) {
                        $fail('Invalid achievement requirements format');
                    }
                }
            ],
            'rewards' => ['required', 'array',
                function ($attribute, $value, $fail) {
                    $rules = Achievement::getRewardRules();
                    $validator = validator($value, $rules);
                    if ($validator->fails()) {
                        $fail('Invalid achievement rewards format');
                    }
                }
            ],
            'icon' => 'nullable|image|max:2048|mimes:jpeg,png,svg'
        ];
    }
}