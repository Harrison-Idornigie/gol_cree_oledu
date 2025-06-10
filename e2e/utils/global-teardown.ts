import { FullConfig } from '@playwright/test';
import { DatabaseHelper } from './database-helpers';

/**
 * Global teardown for E2E tests
 * 
 * This runs once after all tests complete and cleans up the testing environment:
 * - Cleans up test databases
 * - Removes test data
 * - Resets environment
 */
async function globalTeardown(config: FullConfig) {
  console.log('🧹 Starting E2E test environment cleanup...');

  try {
    // Initialize database helper for cleanup
    const dbHelper = new DatabaseHelper();
    await dbHelper.initialize();

    // Clean up test data if configured to do so
    if (process.env.CLEANUP_TEST_DATA !== 'false') {
      console.log('🗑️  Cleaning up test data...');
      await dbHelper.cleanupAllTestData();
    } else {
      console.log('⏭️  Skipping test data cleanup (CLEANUP_TEST_DATA=false)');
    }

    // Close database connections
    await dbHelper.close();

    console.log('✅ E2E test environment cleanup completed successfully!');

  } catch (error) {
    console.error('❌ Failed to cleanup E2E test environment:', error);
    // Don't throw error in teardown to avoid masking test failures
  }
}

export default globalTeardown;
