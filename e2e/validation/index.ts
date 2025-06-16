/**
 * Pre-Test Validation System - Main Export
 * 
 * Provides convenient exports for the validation system components.
 */

// Core components
export { ValidationManager, createValidationManager } from './core/ValidationManager';
export { ValidationPipeline } from './core/ValidationPipeline';
export { ValidationResults, PhaseResult } from './core/ValidationResults';

// Type definitions
export type {
  IValidator,
  ValidationContext,
  ValidationResult,
  ValidationConfig,
  TenantInfo,
  EnvironmentConfig,
  ValidationHelpers,
  ValidationSummary,
  ValidationReport
} from './core/ValidationTypes';

// Validators
export { BasicSeedingValidator } from './validators/SeedingValidator';
export { 
  BackendHealthValidator, 
  FrontendHealthValidator, 
  DatabaseConnectivityValidator 
} from './validators/EnvironmentValidators';
export { 
  TenantDatabaseValidator, 
  AdminUserValidator, 
  TenantIsolationValidator 
} from './validators/TenantValidators';

// Enhanced validators
export {
  TenantStateValidator,
  createTenantStateValidator,
  TenantStateValidators
} from './validators/TenantStateValidator';

// Enhanced core components
export {
  TenantRollbackManager,
  createTenantRollbackManager
} from './core/TenantRollbackManager';

export {
  JobMonitor,
  createJobMonitor
} from './core/JobMonitor';

export {
  PerformanceCollector,
  createPerformanceCollector
} from './core/PerformanceCollector';

// Integration
export {
  PlaywrightValidationIntegrator,
  createPlaywrightIntegrator,
  withValidation
} from './integration/PlaywrightIntegrator';

// Utilities
export {
  validateTenantSetup,
  validateTenantBySlug,
  validateEnvironment,
  shouldSkipValidation,
  createTestValidator,
  withTenantValidation,
  extractTenantSlug,
  ValidationError,
  isValidationError,
  ValidationConfig as ValidationConfigUtils,
  // Enhanced utilities
  withRollbackProtection,
  withPerformanceMonitoring,
  withJobMonitoring,
  withEnhancedValidation,
  createEnhancedTestValidator,
  validateEnhancedTenantSetup
} from '../utils/validation-helpers';

// Configuration
export { 
  defaultValidationConfig, 
  testValidationConfig, 
  getValidationConfig, 
  createEnvironmentConfig 
} from '../config/validation.config';

// Logging
export { ValidationLogger } from './logging/ValidationLogger';

/**
 * Quick start exports for common use cases
 */
export const PreTestValidation = {
  // For global setup enhancement
  enhanceGlobalSetup: async () => {
    const { createPlaywrightIntegrator } = await import('./integration/PlaywrightIntegrator');
    return createPlaywrightIntegrator();
  },
  
  // For test setup
  validateBeforeTest: async (testInfo: any) => {
    const { validateTenantSetup } = await import('../utils/validation-helpers');
    return validateTenantSetup(testInfo);
  },
  
  // For specific tenant validation
  validateTenant: async (tenantSlug: string) => {
    const { validateTenantBySlug } = await import('../utils/validation-helpers');
    return validateTenantBySlug(tenantSlug);
  },
  
  // For environment validation
  validateEnvironment: async () => {
    const { validateEnvironment } = await import('../utils/validation-helpers');
    return validateEnvironment();
  },
  
  // Configuration utilities
  config: {
    skipAll: () => process.env.SKIP_PRE_TEST_VALIDATION = 'true',
    enableAll: () => delete process.env.SKIP_PRE_TEST_VALIDATION,
    setDebug: () => process.env.NODE_ENV = 'test'
  }
};

/**
 * Default export for convenience
 */
export default PreTestValidation;