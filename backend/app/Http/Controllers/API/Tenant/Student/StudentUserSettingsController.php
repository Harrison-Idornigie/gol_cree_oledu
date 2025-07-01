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
        // Only students can access student settings endpoints
        if (!$request->user()->isStudent()) {
            return $this->sendError('Access denied. This endpoint is for students only.', [], 403);
        }

        try {
            $user = $request->user();

            // Get stored settings from user metadata or use defaults
            $userSettings = $user->metadata ?? [];

            // Default settings
            $defaultSettings = [
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

            $settings = [
                'interface_language' => $user->interface_language ?? 'en',
                'notification_preferences' => array_merge(
                    $defaultSettings['notification_preferences'],
                    $userSettings['notification_preferences'] ?? []
                ),
                'learning_preferences' => array_merge(
                    $defaultSettings['learning_preferences'],
                    $userSettings['learning_preferences'] ?? []
                ),
                'privacy_settings' => array_merge(
                    $defaultSettings['privacy_settings'],
                    $userSettings['privacy_settings'] ?? []
                ),
                'accessibility' => array_merge(
                    $defaultSettings['accessibility'],
                    $userSettings['accessibility'] ?? []
                )
            ];

            return $this->sendResponse($settings, 'User settings retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve settings', ['error' => $e->getMessage()], 500);
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
            return $this->sendError('Failed to retrieve available languages', ['error' => $e->getMessage()], 500);
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
        // Only students can access student settings endpoints
        if (!$request->user()->isStudent()) {
            return $this->sendError('Access denied. This endpoint is for students only.', [], 403);
        }

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
            return $this->sendError('Failed to update interface language', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update general user settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSettings(Request $request): JsonResponse
    {
        // Only students can access student settings endpoints
        if (!$request->user()->isStudent()) {
            return $this->sendError('Access denied. This endpoint is for students only.', [], 403);
        }

        $request->validate([
            'interface_language' => 'sometimes|string|size:2|in:en,es,fr,de,it,pt,ru,zh,ja,ko',
            'notification_preferences' => 'sometimes|array',
            'notification_preferences.email_notifications' => 'sometimes|boolean',
            'notification_preferences.push_notifications' => 'sometimes|boolean',
            'notification_preferences.lesson_reminders' => 'sometimes|boolean',
            'learning_preferences' => 'sometimes|array',
            'learning_preferences.daily_goal_minutes' => 'sometimes|integer|min:5|max:480',
            'learning_preferences.difficulty_preference' => 'sometimes|string|in:beginner,intermediate,advanced,adaptive',
            'learning_preferences.audio_autoplay' => 'sometimes|boolean',
            'privacy_settings' => 'sometimes|array',
            'privacy_settings.profile_visibility' => 'sometimes|string|in:public,private',
            'privacy_settings.progress_sharing' => 'sometimes|boolean',
        ]);

        try {
            $user = $request->user();

            // Get current metadata or initialize empty array
            $currentMetadata = $user->metadata ?? [];

            // Update interface language if provided
            if ($request->has('interface_language')) {
                $user->update(['interface_language' => $request->interface_language]);
            }

            // Update settings in metadata
            if ($request->has('notification_preferences')) {
                $currentMetadata['notification_preferences'] = array_merge(
                    $currentMetadata['notification_preferences'] ?? [],
                    $request->notification_preferences
                );
            }

            if ($request->has('learning_preferences')) {
                $currentMetadata['learning_preferences'] = array_merge(
                    $currentMetadata['learning_preferences'] ?? [],
                    $request->learning_preferences
                );
            }

            if ($request->has('privacy_settings')) {
                $currentMetadata['privacy_settings'] = array_merge(
                    $currentMetadata['privacy_settings'] ?? [],
                    $request->privacy_settings
                );
            }

            // Save updated metadata
            $user->update(['metadata' => $currentMetadata]);

            // Return the updated settings
            $settings = [
                'interface_language' => $user->interface_language ?? 'en',
                'notification_preferences' => $currentMetadata['notification_preferences'] ?? [
                    'email_notifications' => true,
                    'push_notifications' => true,
                    'lesson_reminders' => true,
                ],
                'learning_preferences' => $currentMetadata['learning_preferences'] ?? [
                    'daily_goal_minutes' => 30,
                    'difficulty_preference' => 'adaptive',
                    'audio_autoplay' => true,
                ],
                'privacy_settings' => $currentMetadata['privacy_settings'] ?? [
                    'profile_visibility' => 'private',
                    'progress_sharing' => false,
                ],
            ];

            return $this->sendResponse($settings, 'User settings updated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update settings', ['error' => $e->getMessage()], 500);
        }
    }
}
