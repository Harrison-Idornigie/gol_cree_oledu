/**
 * Validation Configuration for Pre-Test Validation System
 * 
 * Defines the default configuration for tenant validation phases,
 * async operations, logging, and fail-fast behavior.
 */

import { ValidationConfig } from '../validation/core/ValidationTypes';

export const defaultValidationConfig: ValidationConfig = {
  phases: {
    environment: {
      enabled: true,
      validators: ['backend-health', 'frontend-health', 'database-connectivity'],
      timeout: 30000
    },
    database: {
      enabled: true,
      validators: ['schema-validation', 'migration-status', 'cleanup-orphaned'],
      timeout: 60000
    },
    tenant: {
      enabled: true,
      validators: ['tenant-database', 'admin-user', 'tenant-isolation'],
      timeout: 45000
    },
    seeding: {
      enabled: true,
      validators: ['basic-seeding-validation'],
      timeout: 30000,
      requiredTables: ['languages', 'roles', 'permissions'],
      minRecordCounts: {
        languages: 1,
        roles: 3, // admin, student, team minimum
        permissions: 10 // basic permissions should exist
      }
    }
  },
  async: {
    tenantCreationTimeout: 60000,
    progressCheckInterval: 1000,
    maxRetries: 3
  },
  logging: {
    level: 'info',
    outputs: ['console', 'json'],
    metricsEnabled: true
  },
  failFast: true,
  skipValidationOnEnvVar: 'SKIP_PRE_TEST_VALIDATION',
  
  // Enhanced features configuration
  rollback: {
    enabled: true,
    autoRollbackOnFailure: true,
    preserveOnSuccess: false
  },
  
  jobMonitoring: {
    enabled: true,
    timeout: 120000, // 2 minutes
    pollInterval: 2000, // 2 seconds
    trackTenantJobs: true,
    trackSeedingJobs: true,
    queues: ['default', 'tenant-creation', 'tenant-seeding'],
    connections: ['database']
  },
  
  performance: {
    enabled: true,
    collectMemory: true,
    collectDatabase: true,
    collectNetwork: true,
    reportFormat: 'console',
    thresholds: {
      maxValidatorDuration: 5000, // 5 seconds
      maxTotalDuration: 30000, // 30 seconds
      maxMemoryUsage: 100 * 1024 * 1024, // 100MB
      maxDatabaseQueries: 50,
      maxNetworkRequests: 20
    }
  },
  
  tenantState: {
    enabled: true,
    validationLevel: 'complete',
    checkFilePermissions: true,
    customValidations: []
  }
};

export const testValidationConfig: ValidationConfig = {
  ...defaultValidationConfig,
  phases: {
    ...defaultValidationConfig.phases,
    environment: {
      ...defaultValidationConfig.phases.environment,
      timeout: 10000 // Shorter timeouts for testing
    },
    database: {
      ...defaultValidationConfig.phases.database,
      timeout: 20000
    },
    tenant: {
      ...defaultValidationConfig.phases.tenant,
      timeout: 15000
    },
    seeding: {
      ...defaultValidationConfig.phases.seeding,
      timeout: 10000
    }
  },
  async: {
    tenantCreationTimeout: 30000,
    progressCheckInterval: 500,
    maxRetries: 2
  },
  logging: {
    level: 'debug',
    outputs: ['console'],
    metricsEnabled: false
  }
};

export function getValidationConfig(): ValidationConfig {
  // Allow environment override for testing
  if (process.env.NODE_ENV === 'test') {
    return testValidationConfig;
  }
  
  // Check for skip validation environment variable
  if (process.env[defaultValidationConfig.skipValidationOnEnvVar!]) {
    return {
      ...defaultValidationConfig,
      phases: {
        environment: { ...defaultValidationConfig.phases.environment, enabled: false },
        database: { ...defaultValidationConfig.phases.database, enabled: false },
        tenant: { ...defaultValidationConfig.phases.tenant, enabled: false },
        seeding: { ...defaultValidationConfig.phases.seeding, enabled: false }
      }
    };
  }
  
  return defaultValidationConfig;
}

export function createEnvironmentConfig(): import('../validation/core/ValidationTypes').EnvironmentConfig {
  return {
    name: process.env.NODE_ENV || 'test',
    backendUrl: process.env.BACKEND_URL || 'http://localhost:8000',
    frontendUrl: process.env.FRONTEND_URL || 'http://localhost:3000',
    apiBaseUrl: process.env.API_BASE_URL || 'http://localhost:8000/api',
    testMode: process.env.NODE_ENV === 'test' || process.env.APP_ENV === 'testing'
  };
}