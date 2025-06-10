import { test, expect, Page } from '@playwright/test';
import { TenantFixtures, FORM_SELECTORS, TenantRegistrationData } from '../fixtures/tenant-fixtures';
import { ApiHelper } from '../utils/api-helpers';
import { DatabaseHelper } from '../utils/database-helpers';

/**
 * End-to-End Tests for Tenant Registration Flow
 * 
 * This test suite covers the complete tenant registration process from
 * frontend form submission to backend tenant creation and database setup.
 * 
 * Test Coverage:
 * - Frontend form validation and submission
 * - API request handling and response validation
 * - Backend tenant creation and database setup
 * - Tenant identification via path-based routing
 * - Error handling and user feedback
 * - Post-registration redirect flow
 */

test.describe('Tenant Registration E2E Flow', () => {
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

  test.beforeEach(async ({ page }) => {
    // Navigate to tenant registration page
    await page.goto('/register-org');
    
    // Wait for page to be fully loaded
    await expect(page.locator('h1')).toContainText('Create Your Organization');
  });

  test.describe('Successful Registration Flow', () => {
    test('should complete full tenant registration with valid data', async ({ page }) => {
      const testData = fixtures.generateValidTenantData();
      
      // Fill out the registration form
      await fillRegistrationForm(page, testData);
      
      // Submit the form
      await page.click(FORM_SELECTORS.submitButton);
      
      // Wait for submission to complete
      await expect(page.locator('text=Creating Organization...')).toBeVisible();
      
      // Wait for redirect to tenant dashboard
      await expect(page).toHaveURL(new RegExp(`/${testData.organizationSlug}/admin`));
      
      // Verify we're on the tenant dashboard
      await expect(page.locator('h1')).toContainText('Dashboard');
      
      // Verify tenant was created in database
      const tenantExists = await dbHelper.tenantExists(testData.organizationSlug!);
      expect(tenantExists).toBe(true);
      
      // Verify tenant database was created and is accessible
      const dbAccessible = await dbHelper.verifyTenantDatabase(testData.organizationSlug!);
      expect(dbAccessible).toBe(true);
      
      // Verify admin user was created in tenant database
      const adminExists = await dbHelper.verifyTenantAdmin(testData.organizationSlug!, testData.adminEmail);
      expect(adminExists).toBe(true);
    });

    test('should auto-generate slug when not provided', async ({ page }) => {
      const testData = fixtures.generateValidTenantData({
        organizationName: 'Springfield Elementary School',
        organizationSlug: '' // Don't provide slug
      });
      
      // Fill form without slug
      await page.fill(FORM_SELECTORS.organizationName, testData.organizationName);
      await page.fill(FORM_SELECTORS.adminName, testData.adminName);
      await page.fill(FORM_SELECTORS.adminEmail, testData.adminEmail);
      await page.fill(FORM_SELECTORS.password, testData.password);
      await page.fill(FORM_SELECTORS.passwordConfirmation, testData.passwordConfirmation);
      
      // Verify slug was auto-generated
      const slugValue = await page.inputValue(FORM_SELECTORS.organizationSlug);
      expect(slugValue).toBe('springfield-elementary-school');
      
      // Submit and verify success
      await page.click(FORM_SELECTORS.submitButton);
      await expect(page).toHaveURL(new RegExp('/springfield-elementary-school/admin'));
    });

    test('should handle slug validation and availability checking', async ({ page }) => {
      const testData = fixtures.generateValidTenantData();
      
      // Fill organization name first
      await page.fill(FORM_SELECTORS.organizationName, testData.organizationName);
      
      // Manually enter a custom slug
      await page.fill(FORM_SELECTORS.organizationSlug, testData.organizationSlug!);
      
      // Trigger slug validation (blur event)
      await page.locator(FORM_SELECTORS.organizationSlug).blur();
      
      // Wait for validation to complete
      await page.waitForTimeout(1000);
      
      // Should show available indicator
      await expect(page.locator('text=Available')).toBeVisible();
      
      // Complete the form and submit
      await fillRemainingFields(page, testData);
      await page.click(FORM_SELECTORS.submitButton);
      
      // Verify successful registration
      await expect(page).toHaveURL(new RegExp(`/${testData.organizationSlug}/admin`));
    });
  });

  test.describe('Form Validation', () => {
    test('should show validation errors for missing required fields', async ({ page }) => {
      const testData = fixtures.generateInvalidTenantData('missing_required');
      
      // Try to submit empty form
      await page.click(FORM_SELECTORS.submitButton);
      
      // Should show validation errors
      await expect(page.locator('text=Organization name is required')).toBeVisible();
      await expect(page.locator('text=Your full name is required')).toBeVisible();
      await expect(page.locator('text=Email address is required')).toBeVisible();
      
      // Form should not be submitted
      await expect(page).toHaveURL('/register-org');
    });

    test('should validate email format', async ({ page }) => {
      const testData = fixtures.generateInvalidTenantData('invalid_email');
      
      await fillRegistrationForm(page, testData);
      await page.click(FORM_SELECTORS.submitButton);
      
      // Should show email validation error
      await expect(page.locator('text=Please enter a valid email address')).toBeVisible();
      await expect(page).toHaveURL('/register-org');
    });

    test('should validate password confirmation match', async ({ page }) => {
      const testData = fixtures.generateInvalidTenantData('password_mismatch');
      
      await fillRegistrationForm(page, testData);
      await page.click(FORM_SELECTORS.submitButton);
      
      // Should show password mismatch error
      await expect(page.locator('text=Passwords do not match')).toBeVisible();
      await expect(page).toHaveURL('/register-org');
    });

    test('should validate slug format', async ({ page }) => {
      const testData = fixtures.generateInvalidTenantData('invalid_slug');
      
      await fillRegistrationForm(page, testData);
      await page.click(FORM_SELECTORS.submitButton);
      
      // Should show slug format error
      await expect(page.locator('text=Organization slug must be 3-50 characters')).toBeVisible();
      await expect(page).toHaveURL('/register-org');
    });
  });

  test.describe('Duplicate Slug Handling', () => {
    test('should prevent registration with duplicate slug', async ({ page }) => {
      const { existingTenant, duplicateTenant } = await fixtures.generateDuplicateSlugData();
      
      // First, create the existing tenant via API
      const firstRegistration = await apiHelper.registerTenantAdmin(existingTenant);
      expect(firstRegistration.success).toBe(true);
      
      // Now try to register with the same slug via UI
      await fillRegistrationForm(page, duplicateTenant);
      await page.click(FORM_SELECTORS.submitButton);
      
      // Should show duplicate slug error
      await expect(page.locator('text=This organization slug is already taken')).toBeVisible();
      await expect(page).toHaveURL('/register-org');
      
      // Verify second tenant was not created
      const secondTenantExists = await dbHelper.tenantExists(duplicateTenant.organizationSlug!);
      expect(secondTenantExists).toBe(false);
    });
  });

  test.describe('API Integration', () => {
    test('should handle API errors gracefully', async ({ page }) => {
      // Mock API failure by using invalid backend URL temporarily
      await page.route('**/api/auth/register-tenant-admin', route => {
        route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({
            success: false,
            message: 'Internal server error'
          })
        });
      });
      
      const testData = fixtures.generateValidTenantData();
      await fillRegistrationForm(page, testData);
      await page.click(FORM_SELECTORS.submitButton);
      
      // Should show error message
      await expect(page.locator('text=An unexpected error occurred')).toBeVisible();
      await expect(page).toHaveURL('/register-org');
    });

    test('should validate API response structure', async ({ page }) => {
      const testData = fixtures.generateValidTenantData();

      // Intercept API call to validate request structure
      let apiRequestBody: any = null;
      let apiCallMade = false;

      await page.route('**/api/auth/register-tenant-admin', async route => {
        const request = route.request();
        try {
          apiRequestBody = JSON.parse(request.postData() || '{}');
          apiCallMade = true;
        } catch (error) {
          console.error('Failed to parse API request body:', error);
        }
        route.continue();
      });

      await fillRegistrationForm(page, testData);
      await page.click(FORM_SELECTORS.submitButton);

      // Wait for API call to complete
      await page.waitForTimeout(3000);

      // Validate that API call was made
      expect(apiCallMade).toBe(true);
      expect(apiRequestBody).toBeDefined();

      // Validate API request structure
      if (apiRequestBody) {
        expect(apiRequestBody).toMatchObject({
          tenant: {
            name: testData.organizationName,
            slug: testData.organizationSlug || '',
            description: testData.organizationDescription || ''
          },
          admin: {
            name: testData.adminName,
            email: testData.adminEmail,
            password: testData.password,
            password_confirmation: testData.passwordConfirmation
          }
        });
      }
    });
  });

  test.describe('Tenant Path-Based Access', () => {
    test('should enable access via path-based tenant identification', async ({ page }) => {
      const testData = fixtures.generateValidTenantData();

      // Complete registration
      await fillRegistrationForm(page, testData);
      await page.click(FORM_SELECTORS.submitButton);
      await expect(page).toHaveURL(new RegExp(`/${testData.organizationSlug}/admin`));

      // Extract auth token from cookies for API testing
      const cookies = await page.context().cookies();
      const authCookie = cookies.find(c => c.name === 'auth_token');
      expect(authCookie).toBeDefined();

      // Test API access via path-based tenant identification
      const tenantAccess = await apiHelper.verifyTenantAccess(testData.organizationSlug!, authCookie!.value);
      expect(tenantAccess.hasAccess).toBe(true);
      expect(tenantAccess.tenantInfo?.slug).toBe(testData.organizationSlug);
    });

    test('should redirect to correct tenant context after registration', async ({ page }) => {
      const testData = fixtures.generateValidTenantData();

      // Complete registration
      await fillRegistrationForm(page, testData);
      await page.click(FORM_SELECTORS.submitButton);

      // Should redirect to tenant admin dashboard
      await expect(page).toHaveURL(new RegExp(`/${testData.organizationSlug}/admin`));

      // Verify tenant context is properly set
      await expect(page.locator(`text=${testData.organizationName}`)).toBeVisible();

      // Test navigation within tenant context
      await page.goto(`/${testData.organizationSlug}/admin/settings`);
      await expect(page).toHaveURL(`/${testData.organizationSlug}/admin/settings`);
    });
  });

  test.describe('Edge Cases and Performance', () => {
    test('should handle special characters in organization names', async ({ page }) => {
      const { specialCharacters } = fixtures.generateEdgeCaseData();

      await fillRegistrationForm(page, specialCharacters);
      await page.click(FORM_SELECTORS.submitButton);

      // Should handle special characters gracefully
      await expect(page).toHaveURL(new RegExp(`/${specialCharacters.organizationSlug}/admin`));

      // Verify tenant was created with special characters
      const tenantExists = await dbHelper.tenantExists(specialCharacters.organizationSlug!);
      expect(tenantExists).toBe(true);
    });

    test('should handle maximum length inputs', async ({ page }) => {
      const { maximalData } = fixtures.generateEdgeCaseData();

      await fillRegistrationForm(page, maximalData);
      await page.click(FORM_SELECTORS.submitButton);

      // Should handle maximum length inputs
      await expect(page).toHaveURL(new RegExp(`/${maximalData.organizationSlug}/admin`));
    });

    test('should complete registration within reasonable time', async ({ page }) => {
      const testData = fixtures.generateValidTenantData();

      const startTime = Date.now();

      await fillRegistrationForm(page, testData);
      await page.click(FORM_SELECTORS.submitButton);
      await expect(page).toHaveURL(new RegExp(`/${testData.organizationSlug}/admin`));

      const endTime = Date.now();
      const registrationTime = endTime - startTime;

      // Registration should complete within 30 seconds
      expect(registrationTime).toBeLessThan(30000);
    });
  });
});

/**
 * Helper function to fill the registration form
 */
async function fillRegistrationForm(page: Page, data: TenantRegistrationData): Promise<void> {
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

/**
 * Helper function to fill remaining form fields (excluding organization name and slug)
 */
async function fillRemainingFields(page: Page, data: TenantRegistrationData): Promise<void> {
  if (data.organizationDescription) {
    await page.fill(FORM_SELECTORS.organizationDescription, data.organizationDescription);
  }
  await page.fill(FORM_SELECTORS.adminName, data.adminName);
  await page.fill(FORM_SELECTORS.adminEmail, data.adminEmail);
  await page.fill(FORM_SELECTORS.password, data.password);
  await page.fill(FORM_SELECTORS.passwordConfirmation, data.passwordConfirmation);
}
