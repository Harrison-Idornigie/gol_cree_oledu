<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\Language;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use App\Models\Word;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

abstract class BaseExerciseSeeder extends Seeder
{
    /**
     * The exercise type this seeder creates
     */
    protected string $exerciseType;
    
    /**
     * The number of exercises to create per language
     */
    protected int $exercisesPerLanguage = 5;
    
    /**
     * The number of words to use per exercise
     */
    protected int $wordsPerExercise = 5;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Authenticate as super admin for content versioning
        $superAdmin = User::where('email', 'test.superadmin@oledu.ca')->first();
        if (!$superAdmin) {
            $this->command->error('Super Admin user not found. Please run SuperAdminSeeder first.');
            return;
        }
        Auth::login($superAdmin);

        // Ensure we have the required languages
        $english = Language::where('code', 'en')->first();
        $spanish = Language::where('code', 'es')->first();
        $plainsCree = Language::where('code', 'crk')->first();

        if (!$english || !$spanish || !$plainsCree) {
            $this->command->error('Required languages not found. Please run LanguageSeeder first.');
            return;
        }

        // Create exercises for each language
        $this->createExercisesForLanguage($english, "English {$this->getExerciseTypeName()} Exercises");
        $this->createExercisesForLanguage($spanish, "Spanish {$this->getExerciseTypeName()} Exercises");
        $this->createExercisesForLanguage($plainsCree, "Plains Cree {$this->getExerciseTypeName()} Exercises");

        $this->command->info("{$this->getExerciseTypeName()} exercises created successfully!");
    }

    /**
     * Create exercises for a specific language
     */
    protected function createExercisesForLanguage(Language $language, string $sectionTitle): void
    {
        // Find or create a lesson for this language
        $lesson = $this->findOrCreateLesson($language);

        // Create a section for exercises
        $section = $this->createSection($lesson, $sectionTitle, $language);

        // Get words for this language
        $words = Word::where('language_id', $language->id)
            ->inRandomOrder()
            ->take(100)
            ->get();

        if ($words->isEmpty()) {
            $this->command->error("No words found for {$language->name}. Please run WordSeeder first.");
            return;
        }

        // Create exercises
        for ($i = 1; $i <= $this->exercisesPerLanguage; $i++) {
            $this->createExercise($section, $words, $i, $language);
        }
    }

    /**
     * Find or create a lesson for the language
     */
    protected function findOrCreateLesson(Language $language): Lesson
    {
        $lesson = Lesson::where('title', 'like', "%{$language->name}%")
            ->first();

        if (!$lesson) {
            $this->command->info("Creating new lesson for {$language->name}");
            $lesson = Lesson::create([
                'title' => "{$language->name} Vocabulary Practice",
                'description' => "Practice your {$language->name} vocabulary with these exercises",
                'slug' => Str::slug("{$language->name} vocabulary practice"),
                'order' => 1,
                'status' => 'published',
                'unit_id' => 1, // Assuming unit 1 exists
            ]);
        }

        return $lesson;
    }

    /**
     * Create a section for the exercises
     */
    protected function createSection(Lesson $lesson, string $title, Language $language): Section
    {
        return Section::create([
            'lesson_id' => $lesson->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => "Practice your {$language->name} vocabulary with {$this->getExerciseTypeName()} exercises",
            'content' => "Complete these {$this->getExerciseTypeName()} exercises to improve your {$language->name} vocabulary",
            'type' => 'practice',
            'order' => 1,
            'requires_previous' => false,
            'xp_reward' => 10,
            'estimated_time' => 10,
            'allow_retry' => true,
            'show_solution' => true,
        ]);
    }

    /**
     * Create a single exercise
     */
    abstract protected function createExercise(Section $section, $words, int $order, Language $language): void;

    /**
     * Get a user-friendly name for the exercise type
     */
    protected function getExerciseTypeName(): string
    {
        return ucwords(str_replace('_', ' ', $this->exerciseType));
    }
}