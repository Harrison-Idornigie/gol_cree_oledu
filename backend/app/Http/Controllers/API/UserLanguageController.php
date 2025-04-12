<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\User;
use App\Models\UserLanguage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserLanguageController extends BaseAPIController
{
    /**
     * Get all languages selected by the authenticated user.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        
        $selectedLanguages = $user->selectedLanguages()
            ->withCount(['learningPaths' => function ($query) {
                $query->where('status', 'published');
            }])
            ->get();
        
        return $this->sendResponse($selectedLanguages);
    }
    
    /**
     * Add a language to the user's selected languages.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language_id' => [
                'required',
                'integer',
                'exists:languages,id',
                Rule::unique('user_languages', 'language_id')->where(function ($query) {
                    return $query->where('user_id', Auth::id());
                }),
            ],
        ]);
        
        $user = Auth::user();
        $language = Language::findOrFail($validated['language_id']);
        
        // Check if language is active
        if (!$language->is_active) {
            return $this->sendError('Language is not active.', [], 400);
        }
        
        // Add language to user's selected languages
        $userLanguage = new UserLanguage([
            'user_id' => $user->id,
            'language_id' => $language->id,
            'is_primary' => $user->selectedLanguages()->count() === 0, // First language is primary
        ]);
        
        $userLanguage->save();
        
        return $this->sendResponse(['success' => true], 'Language added successfully.');
    }
    
    /**
     * Remove a language from the user's selected languages.
     */
    public function destroy(int $languageId): JsonResponse
    {
        $user = Auth::user();
        
        $userLanguage = UserLanguage::where('user_id', $user->id)
            ->where('language_id', $languageId)
            ->first();
        
        if (!$userLanguage) {
            return $this->sendError('Language not found in user\'s selected languages.', [], 404);
        }
        
        // Check if this is the primary language
        $isPrimary = $userLanguage->is_primary;
        
        // Delete the user language
        $userLanguage->delete();
        
        // If this was the primary language, set a new primary language if any exist
        if ($isPrimary) {
            $newPrimary = UserLanguage::where('user_id', $user->id)->first();
            if ($newPrimary) {
                $newPrimary->is_primary = true;
                $newPrimary->save();
            }
        }
        
        return $this->sendResponse(['success' => true], 'Language removed successfully.');
    }
    
    /**
     * Set a language as the user's primary language.
     */
    public function setPrimary(int $languageId): JsonResponse
    {
        $user = Auth::user();
        
        // Check if the language is in user's selected languages
        $userLanguage = UserLanguage::where('user_id', $user->id)
            ->where('language_id', $languageId)
            ->first();
        
        if (!$userLanguage) {
            return $this->sendError('Language not found in user\'s selected languages.', [], 404);
        }
        
        // Reset all primary flags
        UserLanguage::where('user_id', $user->id)
            ->update(['is_primary' => false]);
        
        // Set the new primary language
        $userLanguage->is_primary = true;
        $userLanguage->save();
        
        return $this->sendResponse(['success' => true], 'Primary language set successfully.');
    }
}
