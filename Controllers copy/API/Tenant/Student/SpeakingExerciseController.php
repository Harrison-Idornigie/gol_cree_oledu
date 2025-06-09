<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Services\AttemptTrackingService;
use App\Services\SpeechRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SpeakingExerciseController extends BaseAPIController
{
    protected SpeechRecognitionService $speechService;
    protected AttemptTrackingService $attemptTracker;

    public function __construct(
        SpeechRecognitionService $speechService,
        AttemptTrackingService $attemptTracker
    ) {
        $this->speechService = $speechService;
        $this->attemptTracker = $attemptTracker;
    }

    /**
     * Process a speaking exercise submission
     */
    public function checkAnswer(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|mimes:webm,wav,mp3,ogg|max:10240', // 10MB max
            'exercise_id' => 'required|integer|exists:exercises,id',
            'language' => 'required|string',
            'text_to_speak' => 'required|string',
        ]);

        try {
            // Get the exercise
            $exercise = Exercise::findOrFail($request->exercise_id);
            
            // Store the audio file temporarily
            $audioFile = $request->file('audio');
            $audioPath = $audioFile->store('temp/speech', 'local');
            $fullPath = Storage::disk('local')->path($audioPath);
            
            // Process the audio with the speech recognition service
            $result = $this->speechService->processAudio(
                $fullPath,
                $request->language,
                $request->text_to_speak
            );
            
            // Calculate if the answer is correct (score >= 0.7 is considered correct)
            $isCorrect = $result['score'] >= 0.7;
            
            // Record the attempt
            $this->attemptTracker->recordExerciseAttempt(
                exercise: $exercise,
                userId: Auth::id(),
                userAnswer: json_encode($result),
                isCorrect: $isCorrect,
                timeTaken: $request->input('time_taken', 0)
            );
            
            // Clean up the temporary file
            Storage::disk('local')->delete($audioPath);
            
            return $this->sendResponse($result);
        } catch (\Exception $e) {
            Log::error('Error processing speaking exercise: ' . $e->getMessage(), [
                'exercise_id' => $request->exercise_id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Error processing your recording', 500);
        }
    }
}