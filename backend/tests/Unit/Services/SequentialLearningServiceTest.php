<?php
namespace Tests\Unit\Services;

use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Unit;
use App\Models\Tenants\User;
use App\Models\UserProgress;
use App\Services\SequentialLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequentialLearningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SequentialLearningService $service;
    protected User $user;
    protected LearningPath $learningPath;
    protected Unit $unit1;
    protected Unit $unit2;
    protected Lesson $lesson1;
    protected Lesson $lesson2;
    protected Section $section1;
    protected Section $section2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SequentialLearningService();

        // Create test user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create learning path with units, lessons, and sections
        $this->learningPath = LearningPath::factory()->create([
            'status' => 'published',
        ]);

        // Create units
        $this->unit1 = Unit::factory()->create([
            'learning_path_id' => $this->learningPath->id,
            'order'            => 1,
        ]);

        $this->unit2 = Unit::factory()->create([
            'learning_path_id' => $this->learningPath->id,
            'order'            => 2,
        ]);

        // Create lessons
        $this->lesson1 = Lesson::factory()->create([
            'unit_id' => $this->unit1->id,
            'order'   => 1,
        ]);

        $this->lesson2 = Lesson::factory()->create([
            'unit_id' => $this->unit1->id,
            'order'   => 2,
        ]);

        // Create sections
        $this->section1 = Section::factory()->create([
            'lesson_id'         => $this->lesson1->id,
            'order'             => 1,
            'requires_previous' => true,
        ]);

        $this->section2 = Section::factory()->create([
            'lesson_id'         => $this->lesson1->id,
            'order'             => 2,
            'requires_previous' => true,
        ]);

        // Enroll user in learning path
        UserProgress::create([
            'user_id'        => $this->user->id,
            'trackable_type' => LearningPath::class,
            'trackable_id'   => $this->learningPath->id,
            'status'         => 'in_progress',
        ]);
    }

    /** @test */
    public function first_unit_is_always_unlocked_when_enrolled()
    {
        $this->assertTrue($this->service->isUnitUnlocked($this->unit1));
    }

    /** @test */
    public function second_unit_is_locked_until_first_unit_is_completed()
    {
        // Initially the second unit should be locked
        $this->assertFalse($this->service->isUnitUnlocked($this->unit2));

        // Complete the first unit
        UserProgress::create([
            'user_id'        => $this->user->id,
            'trackable_type' => Unit::class,
            'trackable_id'   => $this->unit1->id,
            'status'         => 'completed',
        ]);

        // Now the second unit should be unlocked
        $this->assertTrue($this->service->isUnitUnlocked($this->unit2));
    }

    /** @test */
    public function first_lesson_is_unlocked_when_unit_is_unlocked()
    {
        $this->assertTrue($this->service->isLessonUnlocked($this->lesson1));
    }

    /** @test */
    public function second_lesson_is_locked_until_first_lesson_is_completed()
    {
        // Initially the second lesson should be locked
        $this->assertFalse($this->service->isLessonUnlocked($this->lesson2));

        // Complete the first lesson
        UserProgress::create([
            'user_id'        => $this->user->id,
            'trackable_type' => Lesson::class,
            'trackable_id'   => $this->lesson1->id,
            'status'         => 'completed',
        ]);

        // Now the second lesson should be unlocked
        $this->assertTrue($this->service->isLessonUnlocked($this->lesson2));
    }

    /** @test */
    public function first_section_is_unlocked_when_lesson_is_unlocked()
    {
        $this->assertTrue($this->service->isSectionUnlocked($this->section1));
    }

    /** @test */
    public function second_section_is_locked_until_first_section_is_completed()
    {
        // Initially the second section should be locked
        $this->assertFalse($this->service->isSectionUnlocked($this->section2));

        // Complete the first section
        UserProgress::create([
            'user_id'        => $this->user->id,
            'trackable_type' => Section::class,
            'trackable_id'   => $this->section1->id,
            'status'         => 'completed',
        ]);

        // Now the second section should be unlocked
        $this->assertTrue($this->service->isSectionUnlocked($this->section2));
    }

    /** @test */
    public function section_is_unlocked_when_requires_previous_is_false()
    {
        // First verify that the lesson is unlocked
        $this->assertTrue($this->service->isLessonUnlocked($this->lesson1));

        // Create a new section with requires_previous = false
        $newSection = Section::factory()->create([
            'lesson_id'         => $this->lesson1->id,
            'order'             => 3,
            'requires_previous' => false,
        ]);

        // The section should be unlocked even though previous section is not completed
        $this->assertTrue($this->service->isSectionUnlocked($newSection));
    }

    /** @test */
    public function get_unlocked_units_returns_correct_units()
    {
        // Initially only the first unit should be unlocked
        $unlockedUnits = $this->service->getUnlockedUnits($this->learningPath);
        $this->assertCount(1, $unlockedUnits);
        $this->assertEquals($this->unit1->id, $unlockedUnits[0]);

        // Complete the first unit
        UserProgress::create([
            'user_id'        => $this->user->id,
            'trackable_type' => Unit::class,
            'trackable_id'   => $this->unit1->id,
            'status'         => 'completed',
        ]);

        // Now both units should be unlocked
        $unlockedUnits = $this->service->getUnlockedUnits($this->learningPath);
        $this->assertCount(2, $unlockedUnits);
        $this->assertEquals($this->unit1->id, $unlockedUnits[0]);
        $this->assertEquals($this->unit2->id, $unlockedUnits[1]);
    }

    /** @test */
    public function get_unlocked_lessons_returns_correct_lessons()
    {
        // Initially only the first lesson should be unlocked
        $unlockedLessons = $this->service->getUnlockedLessons($this->unit1);
        $this->assertCount(1, $unlockedLessons);
        $this->assertEquals($this->lesson1->id, $unlockedLessons[0]);

        // Complete the first lesson
        UserProgress::create([
            'user_id'        => $this->user->id,
            'trackable_type' => Lesson::class,
            'trackable_id'   => $this->lesson1->id,
            'status'         => 'completed',
        ]);

        // Now both lessons should be unlocked
        $unlockedLessons = $this->service->getUnlockedLessons($this->unit1);
        $this->assertCount(2, $unlockedLessons);
        $this->assertEquals($this->lesson1->id, $unlockedLessons[0]);
        $this->assertEquals($this->lesson2->id, $unlockedLessons[1]);
    }
}
