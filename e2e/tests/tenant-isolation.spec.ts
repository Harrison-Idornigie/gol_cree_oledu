import { test, expect } from '@playwright/test';
import { TenantFixtures } from '../fixtures/tenant-fixtures';
import { ApiHelper } from '../utils/api-helpers';
import { DatabaseHelper } from '../utils/database-helpers';

/**
 * Tenant Isolation E2E Tests
 * 
 * This test suite verifies that tenant isolation is properly maintained
 * across different tenant contexts and that data doesn't leak between tenants.
 */

test.describe('Tenant Isolation and Security', () => {
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

  test('should maintain data isolation between tenants', async ({ page, context }) => {
    // Create two separate tenants
    const tenant1Data = fixtures.generateValidTenantData({
      organizationName: 'Tenant One School',
      organizationSlug: 'tenant-one'
    });
    
    const tenant2Data = fixtures.generateValidTenantData({
      organizationName: 'Tenant Two School', 
      organizationSlug: 'tenant-two'
    });

    // Register first tenant via API
    const tenant1Registration = await apiHelper.registerTenantAdmin(tenant1Data);
    expect(tenant1Registration.success).toBe(true);

    // Register second tenant via API
    const tenant2Registration = await apiHelper.registerTenantAdmin(tenant2Data);
    expect(tenant2Registration.success).toBe(true);

    // Login to first tenant
    await page.goto(`/${tenant1Data.organizationSlug}/admin`);
    
    // Try to access second tenant's data via URL manipulation
    await page.goto(`/${tenant2Data.organizationSlug}/admin`);
    
    // Should be redirected to login or show access denied
    // (depending on your authentication middleware implementation)
    const currentUrl = page.url();
    expect(currentUrl).not.toContain(`/${tenant2Data.organizationSlug}/admin`);
  });

  test('should prevent cross-tenant API access', async ({ page }) => {
    // Create two tenants
    const tenant1Data = fixtures.generateValidTenantData();
    const tenant2Data = fixtures.generateValidTenantData();

    // Register both tenants
    const tenant1Reg = await apiHelper.registerTenantAdmin(tenant1Data);
    const tenant2Reg = await apiHelper.registerTenantAdmin(tenant2Data);
    
    expect(tenant1Reg.success).toBe(true);
    expect(tenant2Reg.success).toBe(true);

    // Get token for tenant 1
    const tenant1Token = tenant1Reg.data?.token;
    expect(tenant1Token).toBeDefined();

    // Try to access tenant 2's data using tenant 1's token
    const crossTenantAccess = await apiHelper.verifyTenantAccess(
      tenant2Data.organizationSlug!,
      tenant1Token!
    );

    // Should be denied
    expect(crossTenantAccess.hasAccess).toBe(false);
  });

  test('should handle invalid tenant slugs gracefully', async ({ page }) => {
    // Try to access non-existent tenant
    await page.goto('/non-existent-tenant/admin');
    
    // Should show 404 or redirect to error page
    await expect(page.locator('text=404')).toBeVisible();
  });

  test('should validate tenant status before allowing access', async ({ page }) => {
    const testData = fixtures.generateValidTenantData();
    
    // Register tenant
    const registration = await apiHelper.registerTenantAdmin(testData);
    expect(registration.success).toBe(true);
    
    // Verify tenant is active and accessible
    await page.goto(`/${testData.organizationSlug}/admin`);
    await expect(page.locator('h1')).toContainText('Dashboard');
    
    // TODO: Add test for suspended/inactive tenant access
    // This would require additional API endpoints to change tenant status
  });

  test('should maintain session isolation between browser contexts', async ({ browser }) => {
    const tenant1Data = fixtures.generateValidTenantData();
    const tenant2Data = fixtures.generateValidTenantData();

    // Register both tenants
    await apiHelper.registerTenantAdmin(tenant1Data);
    await apiHelper.registerTenantAdmin(tenant2Data);

    // Create two separate browser contexts (simulating different users)
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();

    const page1 = await context1.newPage();
    const page2 = await context2.newPage();

    // Login to different tenants in each context
    await page1.goto(`/${tenant1Data.organizationSlug}/admin`);
    await page2.goto(`/${tenant2Data.organizationSlug}/admin`);

    // Verify each context maintains its own session
    await expect(page1.locator(`text=${tenant1Data.organizationName}`)).toBeVisible();
    await expect(page2.locator(`text=${tenant2Data.organizationName}`)).toBeVisible();

    // Cleanup
    await context1.close();
    await context2.close();
  });

  test('should handle concurrent tenant registrations', async ({ page }) => {
    // Generate multiple tenant data sets
    const tenantDataSets = Array.from({ length: 3 }, () => fixtures.generateValidTenantData());

    // Register all tenants concurrently
    const registrationPromises = tenantDataSets.map(data => 
      apiHelper.registerTenantAdmin(data)
    );

    const results = await Promise.all(registrationPromises);

    // All registrations should succeed
    results.forEach(result => {
      expect(result.success).toBe(true);
    });

    // Verify all tenants were created in database
    for (const data of tenantDataSets) {
      const exists = await dbHelper.tenantExists(data.organizationSlug!);
      expect(exists).toBe(true);
    }
  });

  test('should prevent SQL injection in tenant identification', async ({ page }) => {
    // Try various SQL injection attempts in tenant slug
    const maliciousSlug = "'; DROP TABLE tenants; --";
    
    await page.goto(`/api/${encodeURIComponent(maliciousSlug)}/auth/me`);
    
    // Should return 404 or proper error, not execute SQL
    const response = await page.waitForResponse(response => 
      response.url().includes(encodeURIComponent(maliciousSlug))
    );
    
    expect(response.status()).toBe(404);
    
    // Verify tenants table still exists by checking if we can create a new tenant
    const validData = fixtures.generateValidTenantData();
    const registration = await apiHelper.registerTenantAdmin(validData);
    expect(registration.success).toBe(true);
  });

  test('should handle special characters in tenant slugs safely', async ({ page }) => {
    // Test various special characters that might cause issues
    const specialSlugs = [
      '../admin',
      '..%2Fadmin',
      'tenant<script>alert(1)</script>',
      'tenant%00admin',
      'tenant\nadmin'
    ];

    for (const slug of specialSlugs) {
      await page.goto(`/${encodeURIComponent(slug)}/admin`);
      
      // Should handle gracefully without errors
      const hasError = await page.locator('text=500').isVisible();
      expect(hasError).toBe(false);
    }
  });
});
