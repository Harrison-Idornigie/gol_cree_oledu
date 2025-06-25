<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Student User Settings Controller
 * 
 * Handles user settings and preferences for students.
 * Access Level: Student
 * Scope: Tenant-specific
 * 
 * This controller allows students to manage their personal
 * settings and learning preferences.
 */
class StudentUserSettingsController extends BaseAPIController
{
    use BelongsToTenant;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct() {}

    /**
     * Get student's settings.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getSettings(Request $request): JsonResponse
    {
        // Students can only access their own settings
        $this->authorize('viewOwnProfile', $request->user());

        try {
            $user = $request->user();

            $settings = [
                'interface_language' => $user->interface_language ?? 'en',
                'notification_preferences' => [
                    'email_notifications' => true,
                    'push_notifications' => true,
                    'learning_reminders' => true,
                    'progress_updates' => true
                ],
                'learning_preferences' => [
                    'daily_goal_minutes' => 30,
                    'preferred_study_time' => 'evening',
                    'difficulty_preference' => 'adaptive',
                    'auto_play_audio' => true
                ],
                'privacy_settings' => [
                    'public_profile' => false,
                    'show_progress' => true,
                    'allow_friend_requests' => true
                ],
                'accessibility' => [
                    'high_contrast' => false,
                    'large_text' => false,
                    'audio_descriptions' => false,
                    'reduced_motion' => false
                ]
            ];

            return $this->sendResponse($settings, 'Settings retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve settings', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get available interface languages.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableLanguages(Request $request): JsonResponse
    {
        try {
            $availableLanguages = [
                ['code' => 'en', 'name' => 'English', 'native_name' => 'English'],
                ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español'],
                ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français'],
                ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch'],
                ['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano'],
                ['code' => 'pt', 'name' => 'Portuguese', 'native_name' => 'Português'],
                ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский'],
                ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文'],
                ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語'],
                ['code' => 'ko', 'name' => 'Korean', 'native_name' => '한국어']
            ];

            $currentLanguage = $request->user()->interface_language ?? 'en';

            return $this->sendResponse([
                'available_languages' => $availableLanguages,
                'current_language' => $currentLanguage
            ], 'Available languages retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve available languages', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update interface language preference.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateInterfaceLanguage(Request $request): JsonResponse
    {
        // Students can only update their own settings
        $this->authorize('updateOwnProfile', $request->user());

        $request->validate([
            'language_code' => 'required|string|size:2|in:en,es,fr,de,it,pt,ru,zh,ja,ko'
        ]);

        try {
            $user = $request->user();
            $user->update(['interface_language' => $request->language_code]);

            return $this->sendResponse([
                'interface_language' => $user->interface_language,
                'message' => 'Interface language updated successfully'
            ], 'Interface language updated successfully.');
        } catch (\Exception $e) {
            return $this->sendErrorResponse('Failed to update interface language', ['error' => $e->getMessage()], 500);
        }
    }
}
