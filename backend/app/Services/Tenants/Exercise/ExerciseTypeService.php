<?php
namespace App\Services\Tenants\Exercise;

use App\Models\Tenants\Exercise;
use App\Services\Tenants\Exercise\ExerciseTypes\ConversationHandler;
use App\Services\Tenants\Exercise\ExerciseTypes\ExerciseTypeHandler;
use App\Services\Tenants\Exercise\ExerciseTypes\FillBlankHandler;
use App\Services\Tenants\Exercise\ExerciseTypes\ListeningHandler;
use App\Services\Tenants\Exercise\ExerciseTypes\MatchingHandler;
use App\Services\Tenants\Exercise\ExerciseTypes\MultipleChoiceHandler;
use App\Services\Tenants\Exercise\ExerciseTypes\PictureHandler;
use App\Services\Tenants\Exercise\ExerciseTypes\SpeakingHandler;
use App\Services\Tenants\Exercise\ExerciseTypes\WritingHandler;

class ExerciseTypeService
{
    /**
     * Get the appropriate handler for the exercise type
     */
    public function getHandler(string $type): ExerciseTypeHandler
    {
        return match ($type) {
            Exercise::TYPE_MULTIPLE_CHOICE => new MultipleChoiceHandler(),
            Exercise::TYPE_FILL_BLANK => new FillBlankHandler(),
            Exercise::TYPE_MATCHING => new MatchingHandler(),
            Exercise::TYPE_WRITING => new WritingHandler(),
            Exercise::TYPE_SPEAKING => new SpeakingHandler(),
            Exercise::TYPE_CONVERSATION => new ConversationHandler(),
            Exercise::TYPE_LISTENING => new ListeningHandler(),
            Exercise::TYPE_PICTURE => new PictureHandler(),
            default => throw new \Exception("Unknown exercise type: {$type}")
        };
    }

    /**
     * Check if the given answer is correct
     */
    public function checkAnswer(Exercise $exercise, $userAnswer): bool
    {
        $handler = $this->getHandler($exercise->type);
        return $handler->checkAnswer($exercise, $userAnswer);
    }

    /**
     * Get a hint or correct answer for the exercise
     */
    public function getHint(Exercise $exercise): mixed
    {
        $handler = $this->getHandler($exercise->type);
        return $handler->getHint($exercise);
    }

    /**
     * Get feedback for the exercise attempt
     */
    public function getFeedback(Exercise $exercise, bool $isCorrect): string
    {
        $handler = $this->getHandler($exercise->type);
        return $handler->getFeedback($exercise, $isCorrect);
    }

    /**
     * Validate the exercise content structure
     */
    public function validateContent(Exercise $exercise): bool
    {
        $handler = $this->getHandler($exercise->type);
        return $handler->validateContent($exercise->content);
    }
}