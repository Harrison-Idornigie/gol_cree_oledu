<?php
namespace App\Services;

use App\Models\Exercise;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\UserProgress;
use Illuminate\Support\Facades\Auth;

class SequentialLearningService
{
    /**
     * Check if a unit is unlocked for the current user
     *
     * @param Unit $unit The unit to check
     * @return bool Whether the unit is unlocked
     */
    public function isUnitUnlocked(Unit $unit): bool
    {
        // First unit in a learning path is always unlocked
        $previousUnits = $unit->learningPath->units()
            ->where('order', '<', $unit->order)
            ->orderBy('order')
            ->get();

        if ($previousUnits->isEmpty()) {
            return true;
        }

        // Check if the user is enrolled in the learning path
        $learningPathProgress = UserProgress::where([
            'user_id'        => Auth::id(),
            'trackable_type' => LearningPath::class,
            'trackable_id'   => $unit->learning_path_id,
        ])->first();

        if (! $learningPathProgress) {
            return false;
        }

        // Check if the previous unit is completed
        $previousUnit         = $previousUnits->last();
        $previousUnitProgress = UserProgress::where([
            'user_id'        => Auth::id(),
            'trackable_type' => Unit::class,
            'trackable_id'   => $previousUnit->id,
        ])->first();

        return $previousUnitProgress && $previousUnitProgress->isCompleted();
    }

    /**
     * Check if a topic is unlocked for the current user
     *
     * @param Topic $topic The topic to check
     * @return bool Whether the topic is unlocked
     */
    public function isTopicUnlocked(Topic $topic): bool
    {
        // First topic in a unit is unlocked if the unit is unlocked
        $previousTopics = $topic->unit->topics()
            ->where('order', '<', $topic->order)
            ->orderBy('order')
            ->get();

        if ($previousTopics->isEmpty()) {
            return $this->isUnitUnlocked($topic->unit);
        }

        // Check if the previous topic is completed
        $previousTopic         = $previousTopics->last();
        $previousTopicProgress = UserProgress::where([
            'user_id'        => Auth::id(),
            'trackable_type' => Topic::class,
            'trackable_id'   => $previousTopic->id,
        ])->first();

        return $previousTopicProgress && $previousTopicProgress->isCompleted();
    }

    /**
     * Check if a lesson is unlocked for the current user
     *
     * @param Lesson $lesson The lesson to check
     * @return bool Whether the lesson is unlocked
     */
    public function isLessonUnlocked(Lesson $lesson): bool
    {
        // First check if the topic is unlocked
        if (! $this->isTopicUnlocked($lesson->topic)) {
            return false;
        }

        // First lesson in a topic is always unlocked if the topic is unlocked
        $previousLessons = $lesson->topic->lessons()
            ->where('order', '<', $lesson->order)
            ->orderBy('order')
            ->get();

        if ($previousLessons->isEmpty()) {
            return true;
        }

        // Check if the previous lesson is completed
        $previousLesson         = $previousLessons->last();
        $previousLessonProgress = UserProgress::where([
            'user_id'        => Auth::id(),
            'trackable_type' => Lesson::class,
            'trackable_id'   => $previousLesson->id,
        ])->first();

        return $previousLessonProgress && $previousLessonProgress->isCompleted();
    }

    /**
     * Check if an exercise is unlocked for the current user
     *
     * @param Exercise $exercise The exercise to check
     * @return bool Whether the exercise is unlocked
     */
    public function isExerciseUnlocked(Exercise $exercise): bool
    {
        // First check if the lesson is unlocked
        if (! $this->isLessonUnlocked($exercise->lesson)) {
            return false;
        }

        // If exercise doesn't require previous completion, it's unlocked if the lesson is unlocked
        if (! $exercise->requires_previous) {
            return true;
        }

        // First exercise in a lesson is always unlocked if the lesson is unlocked
        $previousExercises = $exercise->lesson->exercises()
            ->where('order', '<', $exercise->order)
            ->orderBy('order')
            ->get();

        if ($previousExercises->isEmpty()) {
            return true;
        }

        // Check if the previous exercise is completed
        $previousExercise         = $previousExercises->last();
        $previousExerciseAttempts = $previousExercise->attempts()
            ->where('user_id', Auth::id())
            ->where('is_correct', true)
            ->first();

        return $previousExerciseAttempts !== null;
    }

    /**
     * Get all unlocked units for a learning path
     *
     * @param LearningPath $learningPath The learning path
     * @return array Array of unit IDs that are unlocked
     */
    public function getUnlockedUnits(LearningPath $learningPath): array
    {
        $units             = $learningPath->units()->orderBy('order')->get();
        $unlockedUnitIds   = [];
        $previousCompleted = true; // First unit is always unlocked

        foreach ($units as $unit) {
            if ($previousCompleted) {
                $unlockedUnitIds[] = $unit->id;

                // Check if this unit is completed for next iteration
                $unitProgress = UserProgress::where([
                    'user_id'        => Auth::id(),
                    'trackable_type' => Unit::class,
                    'trackable_id'   => $unit->id,
                ])->first();

                $previousCompleted = $unitProgress && $unitProgress->isCompleted();
            } else {
                break; // Stop once we hit a locked unit
            }
        }

        return $unlockedUnitIds;
    }

    /**
     * Get all unlocked topics for a unit
     *
     * @param Unit $unit The unit
     * @return array Array of topic IDs that are unlocked
     */
    public function getUnlockedTopics(Unit $unit): array
    {
        if (! $this->isUnitUnlocked($unit)) {
            return [];
        }

        $topics            = $unit->topics()->orderBy('order')->get();
        $unlockedTopicIds  = [];
        $previousCompleted = true; // First topic is always unlocked if unit is unlocked

        foreach ($topics as $topic) {
            if ($previousCompleted) {
                $unlockedTopicIds[] = $topic->id;

                // Check if this topic is completed for next iteration
                $topicProgress = UserProgress::where([
                    'user_id'        => Auth::id(),
                    'trackable_type' => Topic::class,
                    'trackable_id'   => $topic->id,
                ])->first();

                $previousCompleted = $topicProgress && $topicProgress->isCompleted();
            } else {
                break; // Stop once we hit a locked topic
            }
        }

        return $unlockedTopicIds;
    }

    /**
     * Get all unlocked lessons for a topic
     *
     * @param Topic $topic The topic
     * @return array Array of lesson IDs that are unlocked
     */
    public function getUnlockedLessonsForTopic(Topic $topic): array
    {
        if (! $this->isTopicUnlocked($topic)) {
            return [];
        }

        $lessons           = $topic->lessons()->orderBy('order')->get();
        $unlockedLessonIds = [];
        $previousCompleted = true; // First lesson is always unlocked if topic is unlocked

        foreach ($lessons as $lesson) {
            if ($previousCompleted) {
                $unlockedLessonIds[] = $lesson->id;

                // Check if this lesson is completed for next iteration
                $lessonProgress = UserProgress::where([
                    'user_id'        => Auth::id(),
                    'trackable_type' => Lesson::class,
                    'trackable_id'   => $lesson->id,
                ])->first();

                $previousCompleted = $lessonProgress && $lessonProgress->isCompleted();
            } else {
                break; // Stop once we hit a locked lesson
            }
        }

        return $unlockedLessonIds;
    }

    /**
     * Get all unlocked lessons for a unit (through topics)
     *
     * @param Unit $unit The unit
     * @return array Array of lesson IDs that are unlocked
     */
    public function getUnlockedLessons(Unit $unit): array
    {
        if (! $this->isUnitUnlocked($unit)) {
            return [];
        }

        $unlockedTopics    = $this->getUnlockedTopics($unit);
        $unlockedLessonIds = [];

        foreach ($unlockedTopics as $topicId) {
            $topic = Topic::find($topicId);
            if ($topic) {
                $unlockedLessonIds = array_merge(
                    $unlockedLessonIds,
                    $this->getUnlockedLessonsForTopic($topic)
                );
            }
        }

        return $unlockedLessonIds;
    }
}
