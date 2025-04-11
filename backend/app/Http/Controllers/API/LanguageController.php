<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Language;
use App\Models\LearningPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LanguageController extends BaseAPIController
{
    /**
     * Display a listing of all active languages.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Language::where('is_active', true);
        
        // Include learning paths count if requested
        if ($request->has('with_learning_paths_count')) {
            $query->withCount(['learningPaths' => function ($query) {
                $query->where('status', 'published');
            }]);
        }
        
        // Include user's progress if requested
        if ($request->has('with_user_progress') && Auth::check()) {
            $userId = Auth::id();
            $query->with(['learningPaths' => function ($query) use ($userId) {
                $query->where('status', 'published')
                    ->with(['progress' => function ($query) use ($userId) {
                        $query->where('user_id', $userId);
                    }]);
            }]);
        }
        
        $languages = $query->get();
        
        return $this->sendResponse($languages);
    }
    
    /**
     * Display the specified language.
     */
    public function show(Language $language): JsonResponse
    {
        if (!$language->is_active) {
            return $this->sendError('Language not found or not active.', [], 404);
        }
        
        return $this->sendResponse($language);
    }
    
    /**
     * Get all languages that have published learning paths.
     */
    public function withLearningPaths(): JsonResponse
    {
        $languages = Language::whereHas('learningPaths', function ($query) {
            $query->where('status', 'published');
        })->where('is_active', true)->get();
        
        return $this->sendResponse($languages);
    }
    
    /**
     * Get learning paths for a specific language.
     */
    public function learningPaths(Language $language, Request $request): JsonResponse
    {
        if (!$language->is_active) {
            return $this->sendError('Language not found or not active.', [], 404);
        }
        
        $query = $language->learningPaths()
            ->where('status', 'published');
            
        // Filter by target level if provided
        if ($request->has('target_level')) {
            $query->where('target_level', $request->target_level);
        }
        
        // Include units if requested
        if ($request->has('with_units')) {
            $query->with(['units' => function ($query) {
                $query->orderBy('order');
            }]);
        }
        
        // Include user progress if requested
        if ($request->has('with_progress') && Auth::check()) {
            $query->with(['progress' => function ($query) {
                $query->where('user_id', Auth::id());
            }]);
        }
        
        $learningPaths = $query->get();
        
        return $this->sendResponse($learningPaths);
    }
    
    /**
     * Get available proficiency levels for a specific language.
     */
    public function proficiencyLevels(Language $language): JsonResponse
    {
        if (!$language->is_active) {
            return $this->sendError('Language not found or not active.', [], 404);
        }
        
        $levels = $language->learningPaths()
            ->where('status', 'published')
            ->distinct()
            ->pluck('target_level')
            ->values();
            
        return $this->sendResponse($levels);
    }
    
    /**
     * Get user's progress summary for a language.
     */
    public function userProgress(Language $language): JsonResponse
    {
        if (!$language->is_active) {
            return $this->sendError('Language not found or not active.', [], 404);
        }
        
        $userId = Auth::id();
        
        // Get all learning paths for this language
        $learningPaths = $language->learningPaths()
            ->where('status', 'published')
            ->with(['progress' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->get();
            
        // Calculate progress statistics
        $totalPaths = $learningPaths->count();
        $completedPaths = $learningPaths->filter(function ($path) {
            return $path->progress->isNotEmpty() && $path->progress->first()->status === 'completed';
        })->count();
        
        $inProgressPaths = $learningPaths->filter(function ($path) {
            return $path->progress->isNotEmpty() && $path->progress->first()->status === 'in_progress';
        })->count();
        
        $notStartedPaths = $totalPaths - $completedPaths - $inProgressPaths;
        
        $progressPercentage = $totalPaths > 0 
            ? round((($completedPaths + ($inProgressPaths * 0.5)) / $totalPaths) * 100, 2) 
            : 0;
            
        return $this->sendResponse([
            'language' => $language->only(['id', 'code', 'name', 'native_name']),
            'total_paths' => $totalPaths,
            'completed_paths' => $completedPaths,
            'in_progress_paths' => $inProgressPaths,
            'not_started_paths' => $notStartedPaths,
            'progress_percentage' => $progressPercentage
        ]);
    }
}