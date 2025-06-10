<?php

namespace Tests\Feature\Admin;

use App\Models\Tenants\User;
use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\UserProgress;
use App\Models\XpHistory;

class AdminProgressTest extends AdminTestCase
{
    protected User $student;
    protected LearningPath $learningPath;
    protected Unit $unit;
    protected Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a student
        $this->student = User::create([
            'name' => 'Student User',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => 'user'
        ]);

        // Create learning content
        $this->learningPath = LearningPath::create([
            'title' => 'Japanese 101',
            'status' => 'published'
        ]);

        $this->unit = Unit::create([
            'learning_path_id' => $this->learningPath->id,
            'title' => 'Basic Greetings',
            'status' => 'published'
        ]);

        $this->lesson = Lesson::create([
            'unit_id' => $this->unit->id,
            'title' => 'Hello and Goodbye',
            'status' => 'published'
        ]);

        // Add some progress data
        UserProgress::create([
            'user_id' => $this->student->id,
            'trackable_type' => LearningPath::class,
            'trackable_id' => $this->learningPath->id,
            'status' => 'in_progress',
            'progress_percentage' => 30
        ]);

        UserProgress::create([
            'user_id' => $this->student->id,
            'trackable_type' => Unit::class,
            'trackable_id' => $this->unit->id,
            'status' => 'completed',
            'progress_percentage' => 100
        ]);

        // Add XP history
        XpHistory::create([
            'user_id' => $this->student->id,
            'amount' => 50,
            'source' => 'lesson_completion',
            'lesson_id' => $this->lesson->id
        ]);
    }

    public function test_admin_can_view_progress_overview()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/progress/overview');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'active_learners' => 1,
                    'completed_lessons' => true,
                    'learning_paths' => [
                        [
                            'id' => $this->learningPath->id,
                            'title' => 'Japanese 101',
                            'user_count' => 1,
                            'completion_rate' => true
                        ]
                    ]
                ]
            ]);
    }

    public function test_admin_can_view_user_progress()
    {
        $response = $this->actingAsAdmin()
            ->getJson("/api/admin/progress/users/{$this->student->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $this->student->id,
                        'name' => 'Student User'
                    ],
                    'current_progress' => [
                        'learning_paths' => [
                            [
                                'id' => $this->learningPath->id,
                                'title' => 'Japanese 101',
                                'progress_percentage' => 30,
                                'status' => 'in_progress'
                            ]
                        ],
                        'completed_units' => [
                            [
                                'id' => $this->unit->id,
                                'title' => 'Basic Greetings'
                            ]
                        ]
                    ],
                    'achievements' => [
                        'total_xp' => 50,
                        'completed_units' => 1,
                        'completed_lessons' => true
                    ]
                ]
            ]);
    }

    public function test_admin_can_filter_progress_by_date_range()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/progress/overview?from=2025-01-01&to=2025-12-31');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'active_learners',
                    'completed_lessons',
                    'learning_paths',
                    'date_range' => ['from', 'to']
                ]
            ]);
    }

    public function test_unauthorized_user_cannot_access_progress_data()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/progress/overview');

        $this->assertUnauthorized($response);

        $response = $this->actingAsUser()
            ->getJson("/api/admin/progress/users/{$this->student->id}");

        $this->assertUnauthorized($response);
    }

    public function test_admin_can_export_progress_report()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/progress/overview?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv')
            ->assertHeader('Content-Disposition', 'attachment; filename=progress-report.csv');
    }
}