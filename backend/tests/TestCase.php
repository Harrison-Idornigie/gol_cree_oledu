<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup central database
        $this->setupCentralDatabase();
    }
    
    /**
     * Setup central database for testing
     */
    protected function setupCentralDatabase(): void
    {
        // Run central database migrations
        $this->artisan('migrate:fresh', [
            '--database' => 'sqlite',
            '--path' => 'database/migrations',
        ]);
        
        // Seed central database with basic data if seeder exists
        try {
            $this->artisan('db:seed', [
                '--database' => 'sqlite',
                '--class' => 'DatabaseSeeder',
            ]);
        } catch (\Exception $e) {
            // Seeder might not exist yet, continue without seeding
        }
    }
}
