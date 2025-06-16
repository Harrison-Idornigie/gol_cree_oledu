/**
 * Enhanced Tenant Test Example
 * 
 * Demonstrates how to use the pre-test validation system
 * with your existing tenant tests for improved reliability.
 */

import { test, expect } from '@playwright/test';
import { TenantFixtures, FORM_SELECTORS } from '../fixtures/tenant-fixtures';
import { ApiHelper } from '../utils/api-helpers';
import { DatabaseHelper } from '../utils/database-helpers';
import { validateTenantSetup, validateTenantBySlug, shouldSkipValidation } from '../utils/validation-helpers';

test.describe('Enhanced Tenant Registration E2E Flow', () => {
  let fixtures: TenantFixtures;
  let apiHelper: ApiHelper;
  let dbHelper: DatabaseHelper;

  test.beforeAll(async () => {
    fixtures = new TenantFixtures();
    apiHelper = new ApiHelper();
    dbHelper = new DatabaseHelper();
    await dbHelper.initialize();
  });

  test.afterAll(async () => {
    await fixtures.cleanup();
    await dbHelper.close();
  });

  // Enhanced beforeEach with validation
  test.beforeEach(async ({ page }, testInfo) => {
    // Skip validation if configured to do so
    if (!shouldSkipValidation()) {
      // Run pre-test validation - this will check:
      // 1. Backend/frontend health
      // 2. Database connectivity 
      // 3. Tenant database setup (if tenant context detected)
      // 4. Basic seeding validation (languages, roles, permissions)
      await validateTenantSetup(testInfo);
    }
    
    // Navigate to tenant registration page
    await page.goto('/register-org');
    await expect(page.locator('h1')).toContainText('Create Your Organization');
  });

  test.describe('Validated Tenant Registration', () => {
    test('should complete registration with pre-validated environment', async ({ page }) => {
      // Since validation already ran in beforeEach, we know:
      // - Backend is healthy and accessible
      // - Database is connected and ready
      // - Required tables exist with minimum data
      
      const testData = fixtures.generateValidTenantData();
      
      // Fill and submit form
      await fillRegistrationForm(page, testData);
      await page.click(FORM_SELECTORS.submitButton);
      
      // Wait for successful redirect
      await expect(page).toHaveURL(new RegExp(`/${testData.organizationSlug}/admin`));
      
      // Verify we're on the tenant dashboard
      await expect(page.locator('h1')).toContainText('Dashboard');
      
      // Additional validation after tenant creation
      // This ensures the new tenant has proper seeding
      await validateTenantBySlug(testData.organizationSlug!);
    });

    test('should handle tenant with specific seeding requirements', async ({ page }) => {
      const testData = fixtures.generateValidTenantData({
        organizationName: 'Language Learning Academy'
      });
      
      // Create tenant
      await fillRegistrationForm(page, testData);
      await page.click(FORM_SELECTORS.submitButton);
      await expect(page).toHaveURL(new RegExp(`/${testData.organizationSlug}/admin`));
      
      // Post-creation validation ensures:
      // - Languages table has at least 1 record
      // - Roles table has at least 3 records (admin, student, team)
      // - Permissions table has at least 10 records
      await validateTenantBySlug(testData.organizationSlug!);
      
      // Now we can safely test language-specific features
      await page.goto(`/${testData.organizationSlug}/admin/languages`);
      
      // This should work because validation confirmed languages exist
      await expect(page.locator('[data-testid="language-list"]')).toBeVisible();
    });
  });

  test.describe('Validation Error Handling', () => {
    test('should provide helpful errors when validation fails', async ({ page }) => {
      // This test demonstrates what happens when validation detects issues
      
      try {
        // Attempt to validate a non-existent tenant
        await validateTenantBySlug('non-existent-tenant-12345');
        
        // If we reach here, validation passed when it shouldn't have
        expect(false).toBe(true); // Force failure
        
      } catch (error) {
        // Validation should fail with helpful error message
        const errorMessage = error instanceof Error ? error.message : String(error);
        expect(errorMessage).toContain('Tenant validation failed');
        console.log('✅ Validation correctly caught missing tenant');
      }
    });
  });

  test.describe('Performance Monitoring', () => {
    test('should track validation performance', async ({ page }) => {
      const startTime = Date.now();
      
      // Create and validate tenant
      const testData = fixtures.generateValidTenantData();
      await fillRegistrationForm(page, testData);
      await page.click(FORM_SELECTORS.submitButton);
      await expect(page).toHaveURL(new RegExp(`/${testData.organizationSlug}/admin`));
      
      const creationTime = Date.now() - startTime;
      
      // Validate the created tenant
      const validationStartTime = Date.now();
      await validateTenantBySlug(testData.organizationSlug!);
      const validationTime = Date.now() - validationStartTime;
      
      // Log performance metrics
      console.log(`📊 Performance Metrics:`);
      console.log(`  - Tenant creation: ${creationTime}ms`);
      console.log(`  - Validation time: ${validationTime}ms`);
      
      // Validation should be reasonably fast
      expect(validationTime).toBeLessThan(30000); // 30 seconds max
    });
  });
});

/**
 * Helper function to fill the registration form
 */
async function fillRegistrationForm(page: any, data: any): Promise<void> {
  await page.fill(FORM_SELECTORS.organizationName, data.organizationName);
  if (data.organizationSlug) {
    await page.fill(FORM_SELECTORS.organizationSlug, data.organizationSlug);
  }
  if (data.organizationDescription) {
    await page.fill(FORM_SELECTORS.organizationDescription, data.organizationDescription);
  }
  await page.fill(FORM_SELECTORS.adminName, data.adminName);
  await page.fill(FORM_SELECTORS.adminEmail, data.adminEmail);
  await page.fill(FORM_SELECTORS.password, data.password);
  await page.fill(FORM_SELECTORS.passwordConfirmation, data.passwordConfirmation);
}