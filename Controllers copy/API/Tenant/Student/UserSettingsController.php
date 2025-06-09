<?php
namespace App\Http\Controllers\API;

use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserSettingsController extends BaseAPIController
{
    /**
     * Get user settings including interface language.
     */
    public function getSettings(): JsonResponse
    {
        $user = Auth::user();

        return $this->sendResponse([
            'interface_language' => $user->interface_language ?? 'en',
        ]);
    }

    /**
     * Update user interface language preference.
     */
    public function updateInterfaceLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language_code' => [
                'required',
                'string',
                'max:5',
                Rule::exists('languages', 'code')->where('is_active', true),
            ],
        ]);

        $user                     = Auth::user();
        $user->interface_language = $validated['language_code'];
        $user->save();

        return $this->sendResponse([
            'success'            => true,
            'interface_language' => $user->interface_language,
        ], 'Interface language updated successfully.');
    }

    /**
     * Get available interface languages.
     */
    public function getAvailableLanguages(): JsonResponse
    {
        $languages = Language::where('is_active', true)
            ->select('id', 'code', 'name', 'native_name')
            ->orderBy('name')
            ->get();

        return $this->sendResponse($languages);
    }
}