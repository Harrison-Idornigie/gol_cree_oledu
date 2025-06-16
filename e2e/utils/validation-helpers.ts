/**
 * Enhanced Validation Helper Utilities
 *
 * Convenient utilities for using the enhanced validation system in tests
 * with rollback protection, performance monitoring, and job tracking.
 */

import { TestInfo } from '@playwright/test';
import { withValidation } from '../validation/integration/PlaywrightIntegrator';
import {
  createValidationManager,
  createEnvironmentConfig,
  createValidationHelpers
} from '../validation/core/ValidationManager';
import { TenantInfo } from '../validation/core/ValidationTypes';
import { DatabaseHelper } from './database-helpers';
import { ApiHelper } from './api-helpers';
import { TenantFixtures } from '../fixtures/tenant-fixtures';

// Import enhanced components
import { createTenantRollbackManager } from '../validation/core/TenantRollbackManager';
import { createJobMonitor } from '../validation/core/JobMonitor';
import { createPerformanceCollector } from '../validation/core/PerformanceCollector';
import { ValidationLogger } from '../validation/logging/ValidationLogger';

/**
 * Pre-test validation utility for easy integration
 */
export const preTestValidation = withValidation();

/**
 * Validate tenant setup before test execution
 * 
 * Usage in tests:
 * ```typescript
 * test.beforeEach(async ({ }, testInfo) => {
 *   await validateTenantSetup(testInfo);
 * });
 * ```
 */
export async function validateTenantSetup(testInfo: TestInfo): Promise<void> {
  await preTestValidation.validateBeforeTest(testInfo);
}

/**
 * Validate specific tenant by slug
 * 
 * Usage:
 * ```typescript
 * await validateTenantBySlug('my-tenant-slug');
 * ```
 */
export async function validateTenantBySlug(tenantSlug: string): Promise<void> {
  const validationManager = createValidationManager();
  const environment = createEnvironmentConfig();
  const helpers = createValidationHelpers(
    new DatabaseHelper(),
    new ApiHelper(),
    new TenantFixtures()
  );

  const tenant: TenantInfo = {
    slug: tenantSlug,
    name: `Tenant ${tenantSlug}`,
    adminEmail: `admin-${tenantSlug}@${process.env.TEST_ADMIN_EMAIL_DOMAIN || 'e2e-test.local'}`
  };

  const results = await validationManager.validateTenant(tenant, environment, helpers);

  if (!results.summary.success) {
    const failures = results.getFailures();
    const errorMessage = `Tenant validation failed for '${tenantSlug}':\n` +
      failures.map(f => `  - ${f.validator}: ${f.message}`).join('\n');
    
    throw new Error(errorMessage);
  }
}

/**
 * Validate environment only (no tenant context)
 * 
 * Usage:
 * ```typescript
 * await validateEnvironment();
 * ```
 */
export async function validateEnvironment(): Promise<void> {
  const validationManager = createValidationManager();
  const environment = createEnvironmentConfig();
  const helpers = createValidationHelpers(
    new DatabaseHelper(),
    new ApiHelper(),
    new TenantFixtures()
  );

  const results = await validationManager.validateEnvironment(environment, helpers);

  if (!results.summary.success) {
    const failures = results.getFailures();
    const errorMessage = 'Environment validation failed:\n' +
      failures.map(f => `  - ${f.validator}: ${f.message}`).join('\n');
    
    throw new Error(errorMessage);
  }
}

/**
 * Check if validation should be skipped
 * 
 * Usage:
 * ```typescript
 * if (shouldSkipValidation()) {
 *   console.log('Skipping validation');
 *   return;
 * }
 * ```
 */
export function shouldSkipValidation(): boolean {
  const validationManager = createValidationManager();
  return validationManager.shouldSkipValidation();
}

/**
 * Create a validation-aware test helper
 * 
 * Usage:
 * ```typescript
 * const validator = createTestValidator();
 * await validator.validateTenant('my-tenant');
 * ```
 */
export function createTestValidator() {
  const validationManager = createValidationManager();
  const environment = createEnvironmentConfig();
  const helpers = createValidationHelpers(
    new DatabaseHelper(),
    new ApiHelper(),
    new TenantFixtures()
  );

  return {
    async validateTenant(tenantSlug: string): Promise<void> {
      const tenant: TenantInfo = {
        slug: tenantSlug,
        name: `Tenant ${tenantSlug}`,
        adminEmail: `admin-${tenantSlug}@${process.env.TEST_ADMIN_EMAIL_DOMAIN || 'e2e-test.local'}`
      };

      const results = await validationManager.validateTenant(tenant, environment, helpers);

      if (!results.summary.success) {
        const failures = results.getFailures();
        throw new Error(`Tenant validation failed: ${failures.map(f => f.message).join(', ')}`);
      }
    },

    async validateEnvironment(): Promise<void> {
      const results = await validationManager.validateEnvironment(environment, helpers);

      if (!results.summary.success) {
        const failures = results.getFailures();
        throw new Error(`Environment validation failed: ${failures.map(f => f.message).join(', ')}`);
      }
    },

    getValidationManager() {
      return validationManager;
    }
  };
}

/**
 * Validation decorator for test functions
 * 
 * Usage:
 * ```typescript
 * const validatedTest = withTenantValidation('my-tenant-slug');
 * 
 * validatedTest('should do something', async ({ page }) => {
 *   // Test code here - tenant validation runs automatically
 * });
 * ```
 */
export function withTenantValidation(tenantSlug: string) {
  return function<T extends (...args: any[]) => any>(testFn: T): T {
    return (async (...args: any[]) => {
      // Run validation before test
      await validateTenantBySlug(tenantSlug);
      
      // Run the actual test
      return testFn(...args);
    }) as T;
  };
}

/**
 * Utility to extract tenant slug from test context
 */
export function extractTenantSlug(testInfo: TestInfo): string | null {
  // Check test title for tenant patterns
  const titleMatch = testInfo.title.match(/tenant[:\-\s]+([a-z0-9\-]+)/i);
  if (titleMatch) {
    return titleMatch[1];
  }

  // Check test file path
  const pathMatch = testInfo.file.match(/[\/\\]([a-z0-9\-]+)[\/\\].*\.spec\.ts$/i);
  if (pathMatch) {
    return pathMatch[1];
  }

  return null;
}

/**
 * Validation error class for better error handling
 */
export class ValidationError extends Error {
  constructor(
    message: string,
    public validator?: string,
    public details?: any
  ) {
    super(message);
    this.name = 'ValidationError';
  }
}

/**
 * Type guards for validation results
 */
export function isValidationError(error: any): error is ValidationError {
  return error instanceof ValidationError;
}

/**
 * Configuration utilities
 */
export const ValidationConfig = {
  /**
   * Enable/disable specific validation phases
   */
  setPhaseEnabled(phase: 'environment' | 'database' | 'tenant' | 'seeding', enabled: boolean) {
    process.env[`VALIDATION_${phase.toUpperCase()}_ENABLED`] = enabled.toString();
  },

  /**
   * Set validation timeout for a phase
   */
  setPhaseTimeout(phase: 'environment' | 'database' | 'tenant' | 'seeding', timeout: number) {
    process.env[`VALIDATION_${phase.toUpperCase()}_TIMEOUT`] = timeout.toString();
  },

  /**
   * Skip all validation
   */
  skipAll() {
    process.env.SKIP_PRE_TEST_VALIDATION = 'true';
  },

  /**
   * Enable all validation
   */
  enableAll() {
    delete process.env.SKIP_PRE_TEST_VALIDATION;
  }
};

/**
 * Enhanced validation helpers with rollback protection
 */
export function withRollbackProtection<T extends (...args: any[]) => any>(testFn: T): T {
  return (async (...args: any[]) => {
    const logger = new ValidationLogger({ level: 'info', outputs: ['console'], metricsEnabled: true });
    const dbHelper = new DatabaseHelper();
    const rollbackManager = createTenantRollbackManager(logger, dbHelper);
    
    // Extract tenant slug from test context or generate one
    const tenantSlug = dbHelper.generateTestTenantSlug();
    
    try {
      // Create rollback snapshot
      rollbackManager.createSnapshot(tenantSlug);
      
      // Run the test function
      const result = await testFn(...args);
      
      return result;
    } catch (error) {
      // Auto-rollback on failure
      await rollbackManager.autoRollbackOnFailure(tenantSlug, error instanceof Error ? error : new Error('Test failed'));
      throw error;
    }
  }) as T;
}

/**
 * Enhanced validation with performance monitoring
 */
export function withPerformanceMonitoring<T extends (...args: any[]) => any>(testFn: T): T {
  return (async (...args: any[]) => {
    const logger = new ValidationLogger({ level: 'info', outputs: ['console'], metricsEnabled: true });
    const performanceCollector = createPerformanceCollector(logger, {
      enabled: true,
      collectMemory: true,
      collectDatabase: true,
      collectNetwork: true,
      reportFormat: 'console',
      thresholds: {
        maxValidatorDuration: 5000,
        maxTotalDuration: 30000,
        maxMemoryUsage: 100 * 1024 * 1024,
        maxDatabaseQueries: 50,
        maxNetworkRequests: 20
      }
    });
    
    const sessionId = performanceCollector.startSession('test-performance');
    performanceCollector.startPhase('test-execution');
    
    try {
      const result = await testFn(...args);
      return result;
    } finally {
      performanceCollector.endPhase();
      const report = performanceCollector.endSession();
      
      if (report && report.summary.performanceScore < 80) {
        logger.warn(`⚠️ Performance score: ${report.summary.performanceScore}/100`);
      }
    }
  }) as T;
}

/**
 * Enhanced validation with job monitoring
 */
export function withJobMonitoring<T extends (...args: any[]) => any>(testFn: T): T {
  return (async (...args: any[]) => {
    const logger = new ValidationLogger({ level: 'info', outputs: ['console'], metricsEnabled: true });
    const dbHelper = new DatabaseHelper();
    const jobMonitor = createJobMonitor(logger, dbHelper, {
      pollInterval: 2000,
      timeout: 120000,
      maxRetries: 3,
      queues: ['default', 'tenant-creation'],
      connections: ['database'],
      tenantJobTypes: ['tenant-setup', 'tenant-seed']
    });
    
    const tenantSlug = dbHelper.generateTestTenantSlug();
    
    try {
      // Start monitoring before test
      const monitoringPromise = jobMonitor.monitorTenantCreation(tenantSlug);
      
      // Run the test
      const result = await testFn(...args);
      
      // Wait for job completion
      await monitoringPromise;
      
      return result;
    } catch (error) {
      logger.error('Test failed during job monitoring:', error);
      throw error;
    }
  }) as T;
}

/**
 * Complete enhanced validation with all features
 */
export function withEnhancedValidation<T extends (...args: any[]) => any>(testFn: T): T {
  return withRollbackProtection(
    withPerformanceMonitoring(
      withJobMonitoring(testFn)
    )
  );
}

/**
 * Create enhanced test validator with all features
 */
export function createEnhancedTestValidator() {
  const validationManager = createValidationManager({
    rollback: { enabled: true, autoRollbackOnFailure: true, preserveOnSuccess: false },
    jobMonitoring: { enabled: true, timeout: 120000, pollInterval: 2000, trackTenantJobs: true, trackSeedingJobs: true, queues: ['default'], connections: ['database'] },
    performance: {
      enabled: true,
      collectMemory: true,
      collectDatabase: true,
      collectNetwork: true,
      reportFormat: 'console',
      thresholds: {
        maxValidatorDuration: 5000,
        maxTotalDuration: 30000,
        maxMemoryUsage: 100 * 1024 * 1024,
        maxDatabaseQueries: 50,
        maxNetworkRequests: 20
      }
    },
    tenantState: { enabled: true, validationLevel: 'complete', checkFilePermissions: true, customValidations: [] }
  });
  
  const environment = createEnvironmentConfig();
  const helpers = createValidationHelpers(
    new DatabaseHelper(),
    new ApiHelper(),
    new TenantFixtures()
  );

  return {
    async validateTenant(tenantSlug: string): Promise<void> {
      const tenant: TenantInfo = {
        slug: tenantSlug,
        name: `Enhanced Tenant ${tenantSlug}`,
        adminEmail: `admin-${tenantSlug}@${process.env.TEST_ADMIN_EMAIL_DOMAIN || 'e2e-test.local'}`
      };

      const results = await validationManager.validateTenant(tenant, environment, helpers);

      if (!results.summary.success) {
        const failures = results.getFailures();
        throw new ValidationError(`Enhanced tenant validation failed: ${failures.map(f => f.message).join(', ')}`);
      }
    },

    async validateEnvironment(): Promise<void> {
      const results = await validationManager.validateEnvironment(environment, helpers);

      if (!results.summary.success) {
        const failures = results.getFailures();
        throw new ValidationError(`Enhanced environment validation failed: ${failures.map(f => f.message).join(', ')}`);
      }
    },

    getValidationManager() {
      return validationManager;
    }
  };
}

/**
 * Enhanced tenant setup validation with all new features
 */
export async function validateEnhancedTenantSetup(testInfo: TestInfo): Promise<void> {
  const validator = createEnhancedTestValidator();
  
  // Extract tenant slug from test context
  const tenantSlug = extractTenantSlug(testInfo) || 'default-test-tenant';
  
  try {
    await validator.validateTenant(tenantSlug);
  } catch (error) {
    console.error(`Enhanced tenant validation failed for ${tenantSlug}:`, error);
    throw error;
  }
}