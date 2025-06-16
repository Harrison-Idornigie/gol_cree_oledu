/**
 * Playwright Integration for Pre-Test Validation
 * 
 * Integrates the validation system with Playwright test runner,
 * providing hooks for global setup and individual test validation.
 */

import { FullConfig, TestInfo } from '@playwright/test';
import { 
  ValidationManager, 
  createValidationManager, 
  createEnvironmentConfig, 
  createValidationHelpers 
} from '../core/ValidationManager';
import { TenantInfo } from '../core/ValidationTypes';
import { DatabaseHelper } from '../../utils/database-helpers';
import { ApiHelper } from '../../utils/api-helpers';
import { TenantFixtures } from '../../fixtures/tenant-fixtures';

export class PlaywrightValidationIntegrator {
  private validationManager: ValidationManager;
  private environmentValidated: boolean = false;

  constructor() {
    this.validationManager = createValidationManager();
  }

  /**
   * Enhance existing global setup with validation
   */
  async enhanceGlobalSetup(originalSetup: (config: FullConfig) => Promise<void>): Promise<(config: FullConfig) => Promise<void>> {
    return async (config: FullConfig) => {
      // Run original setup first
      console.log('🔧 Running original global setup...');
      await originalSetup(config);

      // Skip validation if configured
      if (this.validationManager.shouldSkipValidation()) {
        console.log('⏭️ Skipping pre-test validation');
        return;
      }

      // Run environment validation
      console.log('🚀 Running pre-test validation...');
      await this.runEnvironmentValidation();
      
      console.log('✅ Pre-test validation completed');
    };
  }

  /**
   * Run environment-level validation (no tenant context)
   */
  async runEnvironmentValidation(): Promise<void> {
    if (this.environmentValidated) {
      return; // Already validated
    }

    try {
      const environment = createEnvironmentConfig();
      const helpers = this.createHelpers();

      const results = await this.validationManager.validateEnvironment(environment, helpers);

      if (!results.summary.success) {
        const failures = results.getFailures();
        const errorMessage = `Environment validation failed with ${failures.length} errors:\n` +
          failures.map(f => `  - ${f.validator}: ${f.message}`).join('\n');
        
        throw new Error(errorMessage);
      }

      this.environmentValidated = true;
    } catch (error) {
      console.error('❌ Environment validation failed:', error);
      throw error;
    }
  }

  /**
   * Validate tenant setup before running tenant-specific tests
   */
  async validateTenantForTest(testInfo: TestInfo): Promise<void> {
    const tenantSlug = this.extractTenantFromTest(testInfo);
    
    if (!tenantSlug) {
      // No tenant context, skip tenant validation
      return;
    }

    try {
      const tenant = await this.getTenantInfo(tenantSlug);
      const environment = createEnvironmentConfig();
      const helpers = this.createHelpers();

      const results = await this.validationManager.validateTenant(tenant, environment, helpers);

      if (!results.summary.success) {
        const failures = results.getFailures();
        const errorMessage = `Tenant validation failed for '${tenantSlug}' with ${failures.length} errors:\n` +
          failures.map(f => `  - ${f.validator}: ${f.message}`).join('\n') +
          '\n\nSuggestions:\n' +
          failures.filter(f => f.details?.suggestion)
            .map(f => `  - ${f.details.suggestion}`)
            .join('\n');
        
        throw new Error(errorMessage);
      }

    } catch (error) {
      console.error(`❌ Tenant validation failed for '${tenantSlug}':`, error);
      throw error;
    }
  }

  /**
   * Create a pre-test hook for individual tests
   */
  createPreTestHook() {
    return async (testInfo: TestInfo) => {
      // Ensure environment is validated first
      if (!this.environmentValidated) {
        await this.runEnvironmentValidation();
      }

      // Then validate tenant if applicable
      await this.validateTenantForTest(testInfo);
    };
  }

  /**
   * Extract tenant slug from test context
   */
  private extractTenantFromTest(testInfo: TestInfo): string | null {
    // Check test title for tenant patterns
    const titleMatch = testInfo.title.match(/tenant[:\-\s]+([a-z0-9\-]+)/i);
    if (titleMatch) {
      return titleMatch[1];
    }

    // Check test file path for tenant patterns
    const pathMatch = testInfo.file.match(/[\/\\]([a-z0-9\-]+)[\/\\].*\.spec\.ts$/i);
    if (pathMatch) {
      return pathMatch[1];
    }

    // Check for tenant in test annotations or tags
    const annotations = testInfo.annotations || [];
    for (const annotation of annotations) {
      if (annotation.type === 'tenant' && annotation.description) {
        return annotation.description;
      }
    }

    return null;
  }

  /**
   * Get tenant information for validation
   */
  private async getTenantInfo(tenantSlug: string): Promise<TenantInfo> {
    try {
      const helpers = this.createHelpers();
      const dbHelper = helpers.dbHelper;

      // Get tenant details from database
      const tenant = await dbHelper.getTenant(tenantSlug);
      
      if (!tenant) {
        throw new Error(`Tenant '${tenantSlug}' not found`);
      }

      return {
        slug: tenantSlug,
        name: tenant.name || `Tenant ${tenantSlug}`,
        id: tenant.id,
        adminEmail: this.inferAdminEmail(tenantSlug),
        status: tenant.status || 'active'
      };
    } catch (error) {
      // If we can't get tenant info, create minimal info for validation
      return {
        slug: tenantSlug,
        name: `Tenant ${tenantSlug}`,
        adminEmail: this.inferAdminEmail(tenantSlug)
      };
    }
  }

  /**
   * Create validation helpers from existing utilities
   */
  private createHelpers() {
    const dbHelper = new DatabaseHelper();
    const apiHelper = new ApiHelper();
    const fixtures = new TenantFixtures();

    return createValidationHelpers(dbHelper, apiHelper, fixtures);
  }

  /**
   * Infer admin email from tenant slug
   */
  private inferAdminEmail(tenantSlug: string): string {
    const domain = process.env.TEST_ADMIN_EMAIL_DOMAIN || 'e2e-test.local';
    return `admin-${tenantSlug}@${domain}`;
  }
}

/**
 * Factory function to create the integrator
 */
export function createPlaywrightIntegrator(): PlaywrightValidationIntegrator {
  return new PlaywrightValidationIntegrator();
}

/**
 * Utility to create validation-aware test fixture
 */
export function withValidation() {
  const integrator = createPlaywrightIntegrator();
  const preTestHook = integrator.createPreTestHook();

  return {
    /**
     * Use this in your test beforeEach hooks
     */
    async validateBeforeTest(testInfo: TestInfo): Promise<void> {
      await preTestHook(testInfo);
    },

    /**
     * Get the integrator instance for advanced usage
     */
    getIntegrator(): PlaywrightValidationIntegrator {
      return integrator;
    }
  };
}