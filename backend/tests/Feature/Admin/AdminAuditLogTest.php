<?php

namespace Tests\Feature\Admin;

use App\Models\LearningPath;
use App\Models\AuditLog;
use App\Models\Tenants\User;

class AdminAuditLogTest extends AdminTestCase
{
    protected LearningPath $learningPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create some test data and generate audit logs
        $this->learningPath = LearningPath::create([
            'title' => 'Original Title',
            'description' => 'Original description',
            'target_level' => 'beginner',
            'status' => 'draft'
        ]);

        // Create audit logs for various actions
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'created',
            'area' => 'learning_paths',
            'auditable_type' => LearningPath::class,
            'auditable_id' => $this->learningPath->id,
            'status' => 'success',
            'performed_at' => now(),
            'changes' => [
                'title' => 'Original Title',
                'description' => 'Original description',
                'target_level' => 'beginner',
                'status' => 'draft'
            ],
            'ip_address' => '127.0.0.1'
        ]);

        // Update learning path to generate another audit log
        $this->learningPath->update([
            'title' => 'Updated Title'
        ]);
    }

    public function test_admin_can_view_audit_logs()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/audit-logs');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'action',
                        'area',
                        'auditable_type',
                        'auditable_id',
                        'status',
                        'performed_at'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total'
                ]
            ])
            ->assertJson([
                'success' => true
            ]);
    }

    public function test_admin_can_filter_audit_logs_by_date_range()
    {
        // Create some audit logs with specific dates
        $oldDate = now()->subDays(15);
        $recentDate = now()->subDays(5);

        // Create an old log (outside our filter range)
        $log1 = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'create',
            'area' => 'test',
            'status' => 'success',
            'performed_at' => $oldDate,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'changes' => ['test' => 'data'],
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => $this->admin->id
        ]);

        // Create a recent log (within our filter range)
        $log2 = AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'update',
            'area' => 'test',
            'status' => 'success',
            'performed_at' => $recentDate,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'changes' => ['test' => 'data'],
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => $this->admin->id
        ]);

        // Define our date range (last 10 days)
        $startDate = now()->subDays(10)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        // Use query string parameters instead of request body
        $response = $this->actingAsAdmin()
            ->getJson("/api/admin/audit-logs?start_date={$startDate}&end_date={$endDate}");

        // Verify the response structure
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'action',
                        'area',
                        'auditable_type',
                        'auditable_id',
                        'status',
                        'performed_at'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total'
                ]
            ]);

        // Verify that only the recent log is included in the results
        $responseData = $response->json('data');
        
        // Get all the IDs from the response
        $logIds = collect($responseData)->pluck('id')->toArray();
        
        // Verify the recent log is included
        $this->assertContains($log2->id, $logIds, "Recent log should be included in results");
        
        // Verify the old log is not included
        $this->assertNotContains($log1->id, $logIds, "Old log should not be included in results");
    }

    public function test_admin_can_filter_audit_logs_by_area()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/audit-logs?area=learning_paths');

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $log) {
            $this->assertEquals('learning_paths', $log['area']);
        }
    }

    public function test_admin_can_filter_audit_logs_by_action()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/audit-logs?action=created');

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $log) {
            $this->assertEquals('created', $log['action']);
        }
    }

    public function test_admin_can_filter_audit_logs_by_user()
    {
        $response = $this->actingAsAdmin()
            ->getJson("/api/admin/audit-logs?user_id={$this->admin->id}");

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $log) {
            $this->assertEquals($this->admin->id, $log['user_id']);
        }
    }

    public function test_admin_can_view_specific_audit_log()
    {
        $log = AuditLog::first();

        $response = $this->actingAsAdmin()
            ->getJson("/api/admin/audit-logs/{$log->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user_id',
                    'action',
                    'area',
                    'auditable_type',
                    'auditable_id',
                    'status',
                    'performed_at'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $log->id
                ]
            ]);
    }

    public function test_unauthorized_user_cannot_view_audit_logs()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/audit-logs');

        $this->assertUnauthorized($response);
    }

    public function test_audit_log_export()
    {
        // Mock the AdminAuditController's export method
        $this->mock(\App\Http\Controllers\API\Admin\AdminAuditController::class, function ($mock) {
            $mock->shouldReceive('export')
                ->once()
                ->andReturn(response('', 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename=audit-logs.csv'
                ]));
        });

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/audit-logs/export');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename=audit-logs.csv');
    }

    public function test_audit_logs_pagination()
    {
        // Create many audit logs
        for ($i = 0; $i < 30; $i++) {
            AuditLog::create([
                'user_id' => $this->admin->id,
                'action' => 'viewed',
                'area' => 'learning_paths',
                'auditable_type' => LearningPath::class,
                'auditable_id' => $this->learningPath->id,
                'status' => 'success',
                'performed_at' => now(),
                'changes' => [],
                'ip_address' => '127.0.0.1'
            ]);
        }

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/audit-logs?page=2&per_page=15');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total'
                ]
            ]);

        $this->assertEquals(2, $response->json('pagination.current_page'));
        $this->assertEquals(15, $response->json('pagination.per_page'));
    }
}
