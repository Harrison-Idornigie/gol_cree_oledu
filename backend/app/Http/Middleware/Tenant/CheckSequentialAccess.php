<?php

namespace App\Http\Middleware\Tenant;

use App\Models\Tenants\Lesson;
use App\Models\Tenants\Section;
use App\Models\Tenants\Unit;
use App\Services\Tenants\SequentialLearningService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSequentialAccess
{
    protected $sequentialLearningService;

    public function __construct(SequentialLearningService $sequentialLearningService)
    {
        $this->sequentialLearningService = $sequentialLearningService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract the model type and ID from the route parameters
        $routeName = $request->route()->getName();
        
        // Check if we need to validate sequential access for this route
        if (str_contains($routeName, 'units.show')) {
            $unit = $request->route('unit');
            if (!$this->sequentialLearningService->isUnitUnlocked($unit)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This unit is locked. Complete previous units to unlock it.',
                        'locked' => true
                    ], 403);
                }
                
                return redirect()->route('learning-paths.show', $unit->learning_path_id)
                    ->with('error', 'This unit is locked. Complete previous units to unlock it.');
            }
        } elseif (str_contains($routeName, 'lessons.show')) {
            $lesson = $request->route('lesson');
            if (!$this->sequentialLearningService->isLessonUnlocked($lesson)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This lesson is locked. Complete previous lessons to unlock it.',
                        'locked' => true
                    ], 403);
                }
                
                return redirect()->route('units.show', $lesson->unit_id)
                    ->with('error', 'This lesson is locked. Complete previous lessons to unlock it.');
            }
        } elseif (str_contains($routeName, 'sections.show')) {
            $section = $request->route('section');
            if (!$this->sequentialLearningService->isSectionUnlocked($section)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This section is locked. Complete previous sections to unlock it.',
                        'locked' => true
                    ], 403);
                }
                
                return redirect()->route('lessons.show', $section->lesson_id)
                    ->with('error', 'This section is locked. Complete previous sections to unlock it.');
            }
        }

        return $next($request);
    }
}
